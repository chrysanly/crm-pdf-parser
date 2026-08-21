# SETUP.md — Dev-Tooling Bootstrap (run after STARTUP.md, before any feature work)

> **AI INSTRUCTION — run `STARTUP.md` FIRST** (it resolves the Blade/React/Vue stack gate),
> then this file. This is the one-time, **stack-agnostic** bootstrap for a fresh clone of
> this boilerplate — it installs and wires the local development toolchain. Do the steps
> **in order**; after each step, hit the app once (`composer dev` / load a page) to confirm
> nothing broke before moving on.
>
> Read order for the rest of the context stays: `RULES.md` → `ARCHITECTURE.md` →
> `SCHEMA.md` → `DESIGN.md` → `PRD.md`. **But this file (SETUP.md) is step 0.**
>
> Everything here is **local-only / dev-only** except Spatie Permission (feature-flagged).
> Nothing here should ship enabled to production.

---

## Goal

A boilerplate wired for **fast development**: rich local debugging (Debugbar, Telescope,
Pail), editor autocomplete (IDE Helper), static analysis (Larastan), optional RBAC
(Spatie, behind a flag), and convenience composer scripts.

## Order recap

1. Debugbar → 2. Telescope (local-gated) → 3. Pail (already installed) →
4. IDE Helper → 5. Larastan → 6. Spatie + `ENABLE_SPATIE` flag → 7. Composer scripts.

---

## 1. Debugbar (local only)

```bash
composer require barryvdh/laravel-debugbar --dev
```

Auto-enables only when `APP_DEBUG=true`. Lock it down explicitly in `.env`:

```env
DEBUGBAR_ENABLED=true   # set false in the production .env
```

## 2. Telescope (local only)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Make it **truly local-only**:

**a.** In `composer.json`, stop auto-discovery:

```json
"extra": {
    "laravel": {
        "dont-discover": ["laravel/telescope"]
    }
}
```

**b.** Remove the Telescope provider entries from `bootstrap/providers.php`.

**c.** Register it conditionally in `app/Providers/AppServiceProvider.php` `register()`:

```php
public function register(): void
{
    if ($this->app->environment('local')) {
        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        $this->app->register(\App\Providers\TelescopeServiceProvider::class);
    }
}
```

Visit `/telescope` to verify.

## 3. Pail — live log tailing (already installed)

No setup. Use it when debugging:

```bash
php artisan pail
```

## 4. IDE Helper — autocomplete for facades/models

```bash
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models -N
php artisan ide-helper:meta
```

Add the generated files to `.gitignore`:

```gitignore
_ide_helper.php
_ide_helper_models.php
.phpstorm.meta.php
```

## 5. Larastan — catch bugs before runtime

```bash
composer require --dev larastan/larastan
```

Create `phpstan.neon` in the project root:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
    level: 6
```

> Note: `RULES.md §1` targets **level 8+**. Start at 6 for the boilerplate; raise per project.

Run with `vendor/bin/phpstan analyse`.

## 6. Spatie Permission — gated behind `ENABLE_SPATIE`

Install:

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Add to `.env` (and `.env.example`):

```env
ENABLE_SPATIE=true
```

Create `config/features.php`:

```php
<?php

return [
    'spatie' => env('ENABLE_SPATIE', false),
];
```

Run migrations once (empty tables are harmless when disabled):

```bash
php artisan migrate
```

On the `User` model, keep the trait — the flag controls **usage**, not the trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Gate the middleware aliases in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    if (env('ENABLE_SPATIE', false)) {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    }
})
```

When the flag is **off**, bypass permission checks so `can()` calls don't break. In
`app/Providers/AppServiceProvider.php` `boot()`:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    if (! config('features.spatie')) {
        Gate::before(fn () => true); // all checks pass when RBAC is disabled
    }
}
```

Result: routes with `->middleware('role:admin')` only exist when enabled, and
`@can` / `$user->can()` degrade gracefully when disabled.

> **Failure-mode note:** `Gate::before(fn () => true)` makes **everything authorized**
> when Spatie is off — a sensible *boilerplate default* for fast dev. If a project needs
> the opposite (deny-all) failure mode, flip it to `fn () => false`.
>
> **Tension with `RULES.md §5.1` (AuthZ on every route):** the allow-all bypass is a
> dev convenience only. Any project that ships real authorization MUST set
> `ENABLE_SPATIE=true` (or replace the bypass with deny-all) before going to production.

## 7. Composer scripts (quality-of-life)

Add to the `scripts` block in `composer.json`:

```json
"lint": "pint",
"analyse": "phpstan analyse",
"test": "pest"
```

> This boilerplate already defines a `test` script (`artisan test`). Keep whichever
> runner the project standardizes on — `pest` and `artisan test` both run the Pest suite.

## 8. The dev-server feedback loop (mandatory while coding)

> **AI INSTRUCTION:** the running dev server is the primary error signal. Read it after every
> edit — never write code blind and never "hope it compiles".

Keep **one** long-lived dev process in the background for the whole session:

```bash
composer dev     # artisan dev: PHP server + queue worker + vite  (preferred)
# or, frontend only:
npm run dev
```

Rules of the loop:

1. **After every frontend edit** (`.tsx`, `.ts`, `.vue`, `.css`), check the Vite output
   before writing the next file. It names the exact file + line for import resolution
   failures, syntax errors, missing generated route helpers, and Tailwind errors.
2. **Fix reported errors immediately.** Do not batch frontend errors — one broken import
   cascades and makes the next twenty lines of output useless.
3. **Interpret the output:** a quiet `hmr update` = the module compiled. `Failed to resolve
   import`, `[vite] Internal server error`, or a `500` on a `/build/...` asset = that file is
   broken *right now*.
4. **Never start a second dev server.** A port collision produces confusing output and masks
   real errors. Restart the existing one if its output looks stale.
5. **Vite is not the type checker.** It catches resolution/syntax errors only — `npm run
   types:check` (tsc) and `npm run lint:check` still gate "done".
6. **Server side, same discipline:** `php artisan pail` for live Laravel exceptions/logs
   instead of guessing from a rendered error page.
7. **Restart long-lived processes after any change they cache.** A `queue:work` worker boots
   the app once and holds **all PHP code plus `.env`** — an env change (or a bug fix) is
   invisible to it until `php artisan queue:restart`. Same for a sidecar/daemon without
   `--reload`, and `config:cache` freezes `.env` for every process (`config:clear` in dev).
   **When a result contradicts the code you just wrote, suspect a stale process before the
   logic** — re-run it in a fresh process to compare.

---

## Post-bootstrap checklist

- [ ] `composer dev` boots (server + queue + vite) without errors.
- [ ] `/telescope` loads locally and records requests.
- [ ] Debugbar renders on a page when `APP_DEBUG=true`.
- [ ] `vendor/bin/phpstan analyse` runs clean at level 6.
- [ ] IDE helper files generated and git-ignored.
- [ ] Toggling `ENABLE_SPATIE` on/off doesn't break `can()` / routes.
- [ ] Production `.env` sets `APP_DEBUG=false` and `DEBUGBAR_ENABLED=false`.
