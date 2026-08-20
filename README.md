# Laravel + React Boilerplate

A batteries-included starting point for building web apps on **Laravel 13 + React 19 (Inertia 2) + TypeScript + Tailwind 4**. It pairs a modern Laravel starter kit (Fortify auth, Wayfinder, Vite) with three things a fresh skeleton doesn't give you:

1. **An AI engineering context** (`.claude/context/`) — house rules an AI assistant reads *before* writing code, so generated code matches our architecture, security, and naming standards.
2. **Env-toggled feature modules** — turn on PDF, Excel, etc. with a single `.env` flag and one command. No manual wiring.
3. **Gated maintainer commands** — the install/auth scaffolding commands require an authorization signature before they run.

---

## What this is for

Use this repo as the **template for every new client project** so the fourth site ships far faster than the first. It standardizes the toolchain, the layer conventions, and the reusable feature installers across the portfolio — the "how we build" is already decided and encoded, leaving only the "what we build" (see `.claude/context/PRD.md`).

---

## Requirements

- PHP **8.3+** · Composer
- Node **20+** · npm
- A database (MySQL 8.4 / PostgreSQL 17 recommended; SQLite works for a quick spin)

## Quick start

```bash
composer install
npm install

# environment
cp .env.example .env
php artisan key:generate
php artisan migrate

# run everything (server + queue + vite) in one command
composer dev
# → http://localhost:8000
```

---

## The stack gate (run once)

This boilerplate is portable across **Blade / React / Vue**. On a fresh clone, an AI assistant reads `.claude/context/STARTUP.md` first, detects the stack, and asks you to confirm it. The answer is persisted so it's never asked again:

- `.env` → `APP_STACK=react`
- `config/features.php` → `'stack' => env('APP_STACK', 'blade')`
- `.claude/context/PRD.md` → a stack note at the top

**This repository is set up as `react`.**

---

## AI engineering context (`.claude/context/`)

Read in this order (an assistant is instructed to do the same):

| File | Purpose |
|---|---|
| `STARTUP.md` | First-run stack gate (Blade / React / Vue). |
| `SETUP.md` | One-time dev-tooling bootstrap (Debugbar, Telescope, Pail, IDE Helper, Larastan, Spatie). |
| `FEATURES.md` | Env-toggle feature modules + `auth:setup`. |
| `RULES.md` | Non-negotiable engineering rules (naming, layer law, security, performance). |
| `ARCHITECTURE.md` | Where code lives and how layers talk. |
| `SCHEMA.md` | Database conventions and current schema. |
| `DESIGN.md` | Frontend / UI standards. |
| `PRD.md` | What we're building in **this** project (fill per project). |

---

## Feature modules

Optional capabilities are declared in [`config/features.php`](config/features.php). Flip the flag, run one command — the installer requires the package, publishes its config, and drops reusable Service / Job / Export classes into `app/`.

```bash
# in .env
ENABLE_PDF=true
ENABLE_EXCEL=true

php artisan features:install          # install every enabled module
php artisan features:install pdf      # install a single module
php artisan features:install --force  # overwrite generated files
```

| Flag | Package | Generates |
|---|---|---|
| `ENABLE_PDF` | `barryvdh/laravel-dompdf` | `App\Services\Pdf\PdfService`, `App\Jobs\GeneratePdfJob` |
| `ENABLE_EXCEL` | `maatwebsite/excel` | `App\Services\Excel\ExcelService`, `…\Exports\ArrayExport`, `App\Jobs\GenerateExcelJob` |

The installer is idempotent: installed packages and existing files are skipped (use `--force` to overwrite). Stubs live under `stubs/features/<module>/` as `.stub` files so they aren't autoloaded before their package exists.

**Add a module:** add an entry under `modules` in `config/features.php`, drop matching `.stub` files under `stubs/features/<key>/`, then flag it on and run `features:install`.

---

## Authentication

```bash
php artisan auth:setup                                  # interactive picker
php artisan auth:setup login                            # login only, registration off
php artisan auth:setup login-register                   # login + self-service registration
php artisan auth:setup login-social --providers=google
php artisan auth:setup login-register-social \
  --providers=google,company-email --company-domain=acme.ae
```

| Mode | Register? | Social? |
|---|---|---|
| `login` | ✗ | ✗ |
| `login-register` | ✓ | ✗ |
| `login-social` | ✗ | ✓ |
| `login-register-social` | ✓ | ✓ |

- **React/Vue** already ship Fortify + Inertia auth, so the command toggles registration in `config/fortify.php` and wires social on top — it does **not** reinstall Breeze. (The Blade path installs Breeze.)
- **Social** installs Socialite, copies a `SocialiteController` + `routes/social.php`, and adds a `config/services.php` block per driver. Add credentials to `.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`).
- **`company-email`** restricts sign-in to `--company-domain` (stored in `AUTH_ALLOWED_DOMAINS`, enforced in the OAuth callback).
- Choices are persisted to `AUTH_*` in `.env` and surfaced through `config('features.auth.*')`.

Afterwards: `php artisan migrate` and rebuild assets.

---

## Maintainer authorization

`features:install` and `auth:setup` are gated: they ask for an authorization signature before doing anything. The expected value is stored **only as a one-way hash** (constant-time verified) — never in plaintext. Non-interactively, pass `--password=…`.

To gate a new command: `use App\Console\Concerns\RequiresPassword;`, add a `{--password=}` option, and call `$this->confirmPassword()` first in `handle()`. An empty stored signature disables the gate.

---

## Dev tooling

Documented in [`.claude/context/SETUP.md`](.claude/context/SETUP.md) — install on demand. Highlights: Debugbar (`DEBUGBAR_ENABLED`), Telescope (local-only, conditionally registered in `AppServiceProvider`), Pail live logs, IDE Helper, Larastan, and Spatie Permission behind `ENABLE_SPATIE` (with a `Gate::before` bypass when disabled).

```bash
composer lint       # Pint
composer analyse    # Larastan / PHPStan
composer test       # Pint + PHPStan + Pest
```

---

## `.env` flags reference

```env
APP_STACK=react

# RBAC
ENABLE_SPATIE=false

# Feature modules
ENABLE_PDF=false
ENABLE_EXCEL=false

# Auth (managed by `php artisan auth:setup`)
AUTH_MODE=login-register
AUTH_REGISTRATION=true
AUTH_SOCIAL=
AUTH_ALLOWED_DOMAINS=
```

---

## Project layout

```
app/
  Console/
    Commands/InstallFeatures.php   # features:install
    Commands/InstallAuth.php       # auth:setup
    Concerns/RequiresPassword.php  # authorization-gate trait
  Providers/AppServiceProvider.php # Telescope (local) + Spatie Gate bypass
config/
  features.php                     # stack, spatie, auth block, module registry
resources/js/pages/                # Inertia + React pages (welcome.tsx = landing)
stubs/features/{pdf,excel,auth}/   # module stubs, copied into app/ on install
.claude/context/                   # AI engineering context (read first)
```

---

## License

MIT.
