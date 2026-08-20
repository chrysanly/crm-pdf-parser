# FEATURES.md — Env-Toggle Feature Modules & Auth Setup

> Read after `SETUP.md`. This is how the boilerplate turns optional capabilities on
> with a single env flag + one command — no manual wiring.

---

## The idea

Every optional capability is a **module** declared in `config/features.php`. You flip an
`ENABLE_*` flag in `.env`, run one command, and the installer does the rest: installs the
package, publishes its config, and drops reusable **Service / Job / Export** classes into
`app/`.

```
ENABLE_PDF=true         # in .env
php artisan features:install
# → composer requires barryvdh/laravel-dompdf
# → publishes config/dompdf.php
# → creates app/Services/Pdf/PdfService.php + app/Jobs/GeneratePdfJob.php
```

Idempotent: already-installed packages and existing files are skipped. Re-run with
`--force` to overwrite generated files. Install a single module: `features:install pdf`.

## Authorization gate

Both boilerplate commands (`features:install`, `auth:setup`) require maintainer
authorization **before doing anything**:

```
$ php artisan features:install
 Authorization required to continue: ****
```

- The passphrase is known only to the maintainer. It is never stored in plaintext —
  only a one-way hash is kept, and it is verified with a constant-time check.
- Non-interactive: pass `--password=…`.
- To gate a new command: `use App\Console\Concerns\RequiresPassword;`, add a
  `{--password=}` option, and call `$this->confirmPassword()` first in `handle()`.

## Built-in modules

| Flag | Package | Generates |
|---|---|---|
| `ENABLE_PDF=true` | `barryvdh/laravel-dompdf` | `Services/Pdf/PdfService`, `Jobs/GeneratePdfJob` |
| `ENABLE_EXCEL=true` | `maatwebsite/excel` | `Services/Excel/ExcelService`, `Services/Excel/Exports/ArrayExport`, `Jobs/GenerateExcelJob` |

### Usage examples

```php
// PDF — inline / download / store / queue
app(App\Services\Pdf\PdfService::class)->download('invoices.show', ['invoice' => $invoice], "invoice-{$invoice->id}.pdf");
App\Jobs\GeneratePdfJob::dispatch('invoices.show', ['invoice' => $invoice], "invoices/{$invoice->id}.pdf");

// Excel — download / store / queue
app(App\Services\Excel\ExcelService::class)->download($rows, ['Name', 'Email'], 'users.xlsx');
App\Jobs\GenerateExcelJob::dispatch($rows, ['Name', 'Email'], 'exports/users.xlsx');
```

## Adding a new module (e.g. ENABLE_IMAGE)

1. Add an entry under `modules` in `config/features.php` (packages, publish provider, stubs).
2. Drop matching `.stub` files under `stubs/features/<key>/`.
3. `ENABLE_IMAGE=true` in `.env` → `php artisan features:install`.

Stubs are plain PHP kept as `.stub` so they aren't autoloaded/analyzed until the package
that they reference is actually installed.

---

## Authentication setup

One command scaffolds auth (Laravel Breeze for Blade, + Socialite for OAuth):

```bash
php artisan auth:setup                 # interactive picker
php artisan auth:setup login                       # login only (registration disabled)
php artisan auth:setup login-register              # login + self-service registration
php artisan auth:setup login-social --providers=google
php artisan auth:setup login-register-social --providers=google,company-email --company-domain=acme.ae
```

**Modes**

| Mode | Register? | Social? |
|---|---|---|
| `login` | ✗ | ✗ |
| `login-register` | ✓ | ✗ |
| `login-social` | ✗ | ✓ |
| `login-register-social` | ✓ | ✓ |

**Providers** (`--providers`, social modes): comma list.
- `google` (or other OAuth drivers) → wires a `SocialiteController`, `routes/social.php`,
  and a `config/services.php` block. Add creds to `.env`:
  `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`.
- `company-email` → restricts sign-in to the domains in `--company-domain`
  (stored as `AUTH_ALLOWED_DOMAINS`). Enforced in the Socialite callback.

**What it writes to `.env`:** `AUTH_MODE`, `AUTH_REGISTRATION`, `AUTH_SOCIAL`,
`AUTH_ALLOWED_DOMAINS`. All surfaced through `config/features.php` (`features.auth.*`).

After it runs: `php artisan migrate` and `npm install && npm run dev`.

> Note: `breeze:install` also runs `npm install && npm run build`, so Node must be
> available. For React/Vue stacks (see `STARTUP.md`), install Breeze with the matching
> stack instead — this command targets the Blade default.

---

## .env keys reference

Add these to `.env` (and `.env.example`) — feature flags default to `false`/off:

```env
# Feature modules
ENABLE_PDF=false
ENABLE_EXCEL=false

# RBAC (see SETUP.md §6)
ENABLE_SPATIE=false

# Auth (managed by `php artisan auth:setup`)
AUTH_MODE=login-register
AUTH_REGISTRATION=true
AUTH_SOCIAL=
AUTH_ALLOWED_DOMAINS=
# GOOGLE_CLIENT_ID=
# GOOGLE_CLIENT_SECRET=
# GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```
