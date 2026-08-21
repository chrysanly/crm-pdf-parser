<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\RequiresPassword;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\select;

/**
 * Scaffolds authentication for the boilerplate.
 *
 * Modes select registration + social. Registration is toggled where the stack
 * declares it (Fortify config for React/Vue, routes/auth.php for Blade/Breeze).
 * Social modes install Socialite, copy a SocialiteController + routes/social.php,
 * and add a config/services.php block per OAuth driver. `company-email` restricts
 * sign-in to --company-domain. All choices are persisted to AUTH_* in .env.
 */
final class InstallAuth extends Command
{
    use RequiresPassword;

    private const MODES = ['login', 'login-register', 'login-social', 'login-register-social'];

    /**
     * @var string
     */
    protected $signature = 'auth:setup
        {mode? : login | login-register | login-social | login-register-social}
        {--providers=google : Comma list of OAuth drivers (e.g. google,company-email) for social modes}
        {--company-domain= : Domain(s) allowed to sign in when company-email is a provider}
        {--force : Overwrite generated files that already exist}
        {--password= : Maintainer authorization signature (skips the prompt)}';

    /**
     * @var string
     */
    protected $description = 'Scaffold authentication (mode, registration toggle, optional social sign-in)';

    public function handle(): int
    {
        if (! $this->confirmPassword()) {
            return self::FAILURE;
        }

        $mode = $this->resolveMode();

        $registration = str_contains($mode, 'register');
        $social = str_contains($mode, 'social');

        /** @var array<int, string> $providers */
        $providers = $social
            ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('providers')))))
            : [];

        $companyDomains = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('company-domain')),
        )));

        $this->components->info("Configuring auth: {$mode}");

        $this->scaffoldBaseAuth();
        $this->toggleRegistration($registration);

        if ($social) {
            $this->installSocial($providers);
        }

        $this->persistEnv([
            'AUTH_MODE' => $mode,
            'AUTH_REGISTRATION' => $registration ? 'true' : 'false',
            'AUTH_SOCIAL' => implode(',', array_filter($providers, static fn (string $p): bool => $p !== 'company-email')),
            'AUTH_ALLOWED_DOMAINS' => implode(',', $companyDomains),
        ]);

        $this->newLine();
        $this->components->info('Auth setup complete. Run `php artisan migrate` and rebuild assets.');

        return self::SUCCESS;
    }

    private function resolveMode(): string
    {
        $mode = $this->argument('mode');

        if (is_string($mode) && in_array($mode, self::MODES, true)) {
            return $mode;
        }

        if (is_string($mode) && $mode !== '') {
            $this->components->warn("Unknown mode '{$mode}', falling back to picker.");
        }

        // select() is typed int|string because option arrays may be keyed; MODES is
        // a plain list, so the choice is always one of its string values.
        $selected = select(
            label: 'Which authentication mode?',
            options: self::MODES,
            default: 'login-register',
        );

        return is_string($selected) ? $selected : self::MODES[$selected];
    }

    /**
     * Ensure a base auth scaffold exists. The React/Vue starter kit already ships
     * Fortify + Inertia auth, so this is a no-op there; the Blade path installs Breeze.
     */
    private function scaffoldBaseAuth(): void
    {
        $stack = (string) config('features.stack', 'blade');

        if (in_array($stack, ['react', 'vue'], true) || InstalledVersions::isInstalled('laravel/fortify')) {
            $this->components->twoColumnDetail('base auth', '<fg=yellow>Fortify/Inertia already present, skipped</>');

            return;
        }

        if (! InstalledVersions::isInstalled('laravel/breeze')) {
            $this->components->task('composer require laravel/breeze --dev', fn (): bool => $this->process([
                'composer', 'require', 'laravel/breeze', '--dev',
            ]));
        }

        $this->components->task('breeze:install blade --pest', fn (): bool => $this->process([
            PHP_BINARY, base_path('artisan'), 'breeze:install', 'blade', '--pest',
        ]));
    }

    private function toggleRegistration(bool $enabled): void
    {
        // Fortify stack: comment/uncomment Features::registration() in config/fortify.php.
        $fortify = config_path('fortify.php');

        if (File::exists($fortify)) {
            $contents = File::get($fortify);

            $contents = $enabled
                ? preg_replace('/^(\s*)\/\/\s*(Features::registration\(\),)/m', '$1$2', $contents)
                : preg_replace('/^(\s*)(Features::registration\(\),)/m', '$1// $2', $contents);

            File::put($fortify, (string) $contents);
            $this->components->twoColumnDetail('registration (Fortify)', $enabled ? '<fg=green>enabled</>' : '<fg=yellow>disabled</>');
        }

        // Breeze/Blade stack: comment/uncomment the register routes in routes/auth.php.
        $authRoutes = base_path('routes/auth.php');

        if (File::exists($authRoutes) && ! $enabled) {
            $contents = File::get($authRoutes);
            $contents = preg_replace(
                '/^(\s*)(Route::(get|post)\(\'register\'.*;)/m',
                '$1// $2',
                $contents,
            );
            File::put($authRoutes, (string) $contents);
            $this->components->twoColumnDetail('registration (routes/auth.php)', '<fg=yellow>routes commented</>');
        }
    }

    /**
     * @param  array<int, string>  $providers
     */
    private function installSocial(array $providers): void
    {
        if (! InstalledVersions::isInstalled('laravel/socialite')) {
            $this->components->task('composer require laravel/socialite', fn (): bool => $this->process([
                'composer', 'require', 'laravel/socialite',
            ]));
        }

        $this->copyStub(
            'stubs/features/auth/SocialiteController.stub',
            'app/Http/Controllers/Auth/SocialiteController.php',
        );

        $this->copyStub('stubs/features/auth/routes-social.stub', 'routes/social.php');
        $this->requireSocialRoutes();

        foreach ($providers as $provider) {
            if ($provider === 'company-email') {
                continue; // A policy modifier, not an OAuth driver.
            }

            $this->addServicesBlock($provider);
        }
    }

    private function requireSocialRoutes(): void
    {
        $web = base_path('routes/web.php');
        $contents = File::get($web);

        if (str_contains($contents, "require __DIR__.'/social.php';")) {
            return;
        }

        File::put($web, rtrim($contents)."\n\nrequire __DIR__.'/social.php';\n");
        $this->components->twoColumnDetail('routes/web.php', '<fg=green>requires social.php</>');
    }

    private function addServicesBlock(string $provider): void
    {
        $services = config_path('services.php');
        $contents = File::get($services);

        if (preg_match("/['\"]".preg_quote($provider, '/')."['\"]\s*=>/", $contents) === 1) {
            $this->components->twoColumnDetail("services.{$provider}", '<fg=yellow>exists, skipped</>');

            return;
        }

        $upper = Str::upper($provider);
        $block = <<<PHP

    '{$provider}' => [
        'client_id' => env('{$upper}_CLIENT_ID'),
        'client_secret' => env('{$upper}_CLIENT_SECRET'),
        'redirect' => env('{$upper}_REDIRECT_URI'),
    ],
PHP;

        // Insert the block just before the final closing "];" of the return array.
        $updated = preg_replace('/\n\];\s*$/', "\n{$block}\n];\n", $contents, 1);

        File::put($services, (string) $updated);
        $this->components->twoColumnDetail("services.{$provider}", '<fg=green>added</>');
    }

    private function copyStub(string $stub, string $destination): void
    {
        $source = base_path($stub);
        $target = base_path($destination);

        if (! File::exists($source)) {
            $this->components->twoColumnDetail($destination, '<fg=red>stub missing</>');

            return;
        }

        if (File::exists($target) && ! $this->option('force')) {
            $this->components->twoColumnDetail($destination, '<fg=yellow>exists, skipped</>');

            return;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);
        $this->components->twoColumnDetail($destination, '<fg=green>created</>');
    }

    /**
     * Upsert the given keys into .env (append when missing, replace in place otherwise).
     *
     * @param  array<string, string>  $values
     */
    private function persistEnv(array $values): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            $this->components->warn('.env not found; skipped AUTH_* persistence.');

            return;
        }

        $contents = File::get($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote($value);

            $contents = preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents) === 1
                ? (string) preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        File::put($path, $contents);
        $this->components->twoColumnDetail('.env', '<fg=green>AUTH_* updated</>');
    }

    private function quote(string $value): string
    {
        return Str::contains($value, [' ', '#', '"']) ? '"'.addslashes($value).'"' : $value;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function process(array $command): bool
    {
        $process = new Process($command, base_path(), timeout: null);

        $process->run(function (string $_type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful();
    }
}
