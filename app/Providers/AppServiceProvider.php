<?php

namespace App\Providers;

use App\Contracts\ResumeParser;
use App\Services\Parsing\FakeResumeParser;
use App\Services\Parsing\SidecarResumeParser;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope is local-only: excluded from auto-discovery (composer.json
        // "dont-discover") and registered here just for the local environment.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);

            if (class_exists(\App\Providers\TelescopeServiceProvider::class)) {
                $this->app->register(\App\Providers\TelescopeServiceProvider::class);
            }
        }

        $this->registerResumeParser();
    }

    /**
     * The single binding for the Python sidecar boundary (CLAUDE.md §4). Anything
     * that parses depends on the ResumeParser contract, never on a concrete class.
     */
    protected function registerResumeParser(): void
    {
        $this->app->singleton(ResumeParser::class, function ($app): ResumeParser {
            if (config('services.sidecar.driver') !== 'sidecar') {
                return new FakeResumeParser();
            }

            return new SidecarResumeParser(
                http: $app->make(HttpFactory::class),
                filesystem: $app->make(FilesystemFactory::class),
                baseUrl: (string) config('services.sidecar.url'),
                token: (string) config('services.sidecar.token'),
                timeout: (int) config('services.sidecar.timeout'),
                disk: (string) config('crm.resume_disk'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureModels();
    }

    /**
     * When Spatie Permission (RBAC) is disabled, let every authorization check
     * pass so `can()` / policies degrade gracefully in local development. Flip
     * ENABLE_SPATIE=true before shipping real authorization (see SETUP.md §6).
     */
    protected function configureAuthorization(): void
    {
        // The bypass is a local-dev convenience only. It stays OFF while testing,
        // otherwise every Policy test would pass vacuously (RULES §5.1).
        if (! config('features.spatie') && ! $this->app->runningUnitTests()) {
            Gate::before(static fn (): bool => true);
        }
    }

    /**
     * RULES §6.1: an unintended lazy load is a bug, so it throws outside production.
     */
    protected function configureModels(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Single resources arrive flat; paginated collections keep data/links/meta.
        // The TypeScript types in resources/js/types/models.ts mirror exactly that.
        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
