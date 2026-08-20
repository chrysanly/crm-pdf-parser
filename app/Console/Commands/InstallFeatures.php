<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\RequiresPassword;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Installs the env-toggled feature modules declared in config('features.modules').
 *
 * For each enabled (or explicitly named) module: composer require the package(s),
 * publish the vendor config, copy the module's .stub files into app/ as real PHP,
 * then dump an optimized autoloader. Idempotent — installed packages and existing
 * destination files are skipped unless --force is passed.
 */
final class InstallFeatures extends Command
{
    use RequiresPassword;

    /**
     * @var string
     */
    protected $signature = 'features:install
        {feature? : Install a single module by key (e.g. pdf); omit to install all enabled}
        {--force : Overwrite generated files that already exist}
        {--password= : Maintainer authorization signature (skips the prompt)}';

    /**
     * @var string
     */
    protected $description = 'Install env-toggled feature modules (packages, config, service/job stubs)';

    public function handle(): int
    {
        if (! $this->confirmPassword()) {
            return self::FAILURE;
        }

        /** @var array<string, array<string, mixed>> $modules */
        $modules = (array) config('features.modules', []);

        $only = $this->argument('feature');

        if (is_string($only) && $only !== '' && ! array_key_exists($only, $modules)) {
            $this->components->error("Unknown feature module: {$only}");

            return self::FAILURE;
        }

        $targets = is_string($only) && $only !== ''
            ? [$only => $modules[$only]]
            : array_filter($modules, static fn (array $module): bool => (bool) ($module['enabled'] ?? false));

        if ($targets === []) {
            $this->components->info('No feature modules to install. Enable one with its ENABLE_* flag, or name it explicitly.');

            return self::SUCCESS;
        }

        foreach ($targets as $key => $module) {
            $this->installModule((string) $key, $module);
        }

        $this->components->task('Dumping optimized autoloader', fn () => $this->composer(['dump-autoload', '-o']));

        $this->newLine();
        $this->components->info('Feature installation complete.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $module
     */
    private function installModule(string $key, array $module): void
    {
        $this->components->info("Installing feature: {$key}");

        foreach ((array) ($module['packages'] ?? []) as $package) {
            $package = (string) $package;

            if (InstalledVersions::isInstalled($package)) {
                $this->components->twoColumnDetail($package, '<fg=yellow>already installed</>');

                continue;
            }

            $this->components->task("composer require {$package}", fn (): bool => $this->composer(['require', $package]));
        }

        foreach ((array) ($module['publish'] ?? []) as $provider) {
            $provider = (string) $provider;

            $this->components->task("publish {$provider}", fn (): bool => $this->artisan([
                'vendor:publish', '--provider='.$provider, '--force',
            ]));
        }

        foreach ((array) ($module['stubs'] ?? []) as $stub => $destination) {
            $this->copyStub((string) $stub, (string) $destination);
        }
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
     * Run a composer command in a fresh process at the project root.
     *
     * @param  array<int, string>  $arguments
     */
    private function composer(array $arguments): bool
    {
        return $this->process(array_merge(['composer'], $arguments));
    }

    /**
     * Run an artisan command in a fresh process so newly published providers load cleanly.
     *
     * @param  array<int, string>  $arguments
     */
    private function artisan(array $arguments): bool
    {
        return $this->process(array_merge([PHP_BINARY, base_path('artisan')], $arguments));
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
