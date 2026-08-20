---
description: Reproduce the Laravel + Blade boilerplate (dev tooling, env-toggle feature modules, gated install/auth commands, AI context) into a Laravel project
---

Set up this project the same way the Laravel + Blade boilerplate is configured. Target
directory: `$ARGUMENTS` (defaults to the current workspace).

**Preconditions:** this must be a Laravel 11/12/13 app (has `artisan`, `bootstrap/app.php`,
`app/Providers/AppServiceProvider.php`). If not, stop and tell the user.

Work in order. Read a file before editing it. Keep every generated PHP file
`declare(strict_types=1);`, typed, and `final` where it's a leaf class. Do **not** run
`composer require` for optional packages unless the user asks — scaffold the mechanism, let
them install on demand.

## 0. ASK THE STACK FIRST (blocks everything else)

Before creating or changing **any** file, you MUST ask the user which stack this is and
wait for their answer:

> **Which stack is this project?**
> 1. **Laravel + Blade**  2. **Laravel + Vue**  3. **Laravel + React**

Detect a likely answer first (from `composer.json` / `package.json` — `@inertiajs/vue3`,
`@inertiajs/react`, or Blade-only — and `resources/`) and present it as the default, but
**always ask and wait**; never assume. If `APP_STACK` is already set in `.env`, state the
detected value and ask the user to confirm or change it.

Record the confirmed choice in `APP_STACK` (.env), `config/features.php` `stack`, and a
`PRD.md` note. Everything below is the **Laravel + Blade** path; for **Vue** or **React**,
keep §2–§5 identical (they're stack-agnostic) and swap the frontend delta: install Inertia
+ the matching adapter/TypeScript, use `resources/js/pages/*.{vue,tsx}` instead of Blade
views, and adapt the §6 welcome page to the chosen stack. Do not proceed until the stack is
confirmed.

## 1. AI engineering context (`.claude/context/`)

Create/keep these so agents read them first, in this order:
`STARTUP.md` → `SETUP.md` → `FEATURES.md` → `RULES.md` → `ARCHITECTURE.md` → `SCHEMA.md`
→ `DESIGN.md` → `PRD.md`. If `RULES.md` already exists, only update its priority-order
header to list STARTUP/SETUP/FEATURES first; never clobber existing standards.

- **STARTUP.md** — first-run gate: detect the frontend stack from `composer.json` /
  `package.json` / `resources/`, then **ask** the user to confirm **Laravel + Blade /
  React / Vue**. Persist to `APP_STACK` (.env) + `config/features.php` `stack` + a PRD note
  so it only asks once. Then route to SETUP.md + a stack-specific delta.
- **SETUP.md** — stack-agnostic dev-tooling bootstrap (see §2).
- **FEATURES.md** — documents the env-toggle modules, `auth:setup`, and the authorization
  gate (see §3–§5).

## 2. Dev tooling (document in SETUP.md; install only if asked)

Debugbar (`DEBUGBAR_ENABLED`), Telescope (local-gated via `dont-discover` +
conditional `register()` in `AppServiceProvider`), Pail, IDE Helper (+ `.gitignore`
entries), Larastan (`phpstan.neon`), Spatie Permission behind `ENABLE_SPATIE` with a
`Gate::before` bypass when disabled. Add composer scripts `lint`/`analyse`/`test`.

## 3. Feature registry — `config/features.php`

Keys: `stack` (env `APP_STACK`, default `blade`), `spatie` (env `ENABLE_SPATIE`), an
`auth` block (`mode`, `registration`, `social`, `allowed_domains` from `AUTH_*` env), and a
`modules` map of installable features. Seed two modules:

- `pdf` — `ENABLE_PDF`, package `barryvdh/laravel-dompdf`, publish provider, stubs →
  `app/Services/Pdf/PdfService.php` + `app/Jobs/GeneratePdfJob.php`.
- `excel` — `ENABLE_EXCEL`, package `maatwebsite/excel`, publish provider, stubs →
  `app/Services/Excel/ExcelService.php` + `Exports/ArrayExport.php` + `Jobs/GenerateExcelJob.php`.

## 4. Commands

- `app/Console/Commands/InstallFeatures.php` — `features:install {feature?} {--force}
  {--password=}`. Loops `config('features.modules')`; for each enabled (or explicitly
  named) module: `composer require` (skip if `Composer\InstalledVersions::isInstalled`),
  publish vendor config in a fresh `php artisan vendor:publish` process, copy `.stub`
  files into `app/` (skip existing unless `--force`), then `composer dump-autoload -o`.
- `app/Console/Commands/InstallAuth.php` — `auth:setup {mode?} {--providers=google}
  {--company-domain=} {--force} {--password=}`. Modes `login | login-register |
  login-social | login-register-social`. Installs Breeze (Blade) + `breeze:install blade
  --pest`; toggles registration (comment register routes in `routes/auth.php` when off);
  for social installs Socialite, copies `SocialiteController` + `routes/social.php`
  (require it from `routes/web.php`), adds a `config/services.php` block per OAuth driver;
  `company-email` restricts sign-in to `--company-domain` (stored in `AUTH_ALLOWED_DOMAINS`
  and enforced in the callback). Upserts `AUTH_*` keys into `.env`.

Stubs live under `stubs/features/{pdf,excel,auth}/*.stub` (kept as `.stub` so they aren't
autoloaded before their package exists). Generated classes: thin reusable Service wrappers,
queued Jobs, a generic `ArrayExport`, and a `SocialiteController` that honors
`config('features.auth.allowed_domains')` and the registration flag.

## 5. Authorization gate (hashed, disguised)

- `app/Console/Concerns/RequiresPassword.php` — trait with `confirmPassword()`: reads the
  stored hash, prompts (`--password` skips it), verifies with `Hash::check()`
  (constant-time). Empty stored value = gate disabled. Both commands `use` it, declare
  `{--password=}`, and call it first in `handle()`.
- **Ask the maintainer for the passphrase.** Generate a bcrypt hash
  (`php -r "echo password_hash('<pass>', PASSWORD_BCRYPT, ['cost'=>12]);"`) and store ONLY
  the hash — never the plaintext — in a disguised `config/integrity.php` that reads like an
  asset-integrity manifest (decoy `sha384-…` entries + the hash under `signature` with a
  plausible "bundle fingerprint" comment). The trait reads `config('integrity.signature')`.
  Keep all wording neutral ("authorization", "build signature"); never name it a password
  or reveal its location in docs.

## 6. Welcome page + `.env`

- Rewrite `resources/views/welcome.blade.php` as a one-screen, responsive landing (Blade +
  Tailwind, dark-mode aware): stack badges, "Boilerplate · Laravel + Blade", a responsive
  grid (1 col mobile → 2 cols `sm+`) of **Start developing**, **Debugging & tooling**,
  **Feature modules**, **Authentication**, a `🔒 gated` note, and a
  `https://picsum.photos/...?random={{ rand() }}` image panel. No horizontal scroll; wide
  code blocks use `overflow-x-auto`.
- Add flags to `.env` and `.env.example`: `APP_STACK`, `ENABLE_SPATIE`, `ENABLE_PDF`,
  `ENABLE_EXCEL`, `AUTH_MODE`, `AUTH_REGISTRATION`, `AUTH_SOCIAL`, `AUTH_ALLOWED_DOMAINS`.

## 7. Verify & report

Run `php -l` on new PHP files and `php artisan list` (confirm `features:install` and
`auth:setup` register). Test the gate rejects a wrong passphrase and accepts the real one.
Update `README.md` for the boilerplate. Report what was created and the two manual steps:
adding `.env` flags and running installs on demand.
