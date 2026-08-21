# CLAUDE.md — crm-pdf-parser

> **Read this file first, every session.** It is the router: it tells you which context
> files govern, where this repo *deviates* from them, and what we are actually building.
> It never restates the rules — it points at them.

---

## 1. Context load order (non-negotiable)

The engineering standard lives in [.claude/context/](.claude/context/). Read in this order
before writing any code:

| # | File | What it governs |
|---|---|---|
| 0 | [STARTUP.md](.claude/context/STARTUP.md) | First-run stack gate. **Already satisfied** — see §2. Do not ask the Blade/React/Vue question again. |
| 1 | [SETUP.md](.claude/context/SETUP.md) | Dev-tooling bootstrap (Debugbar, Telescope, Pail, IDE Helper, Larastan, Spatie). See §5 for current state. |
| 2 | [FEATURES.md](.claude/context/FEATURES.md) | `ENABLE_*` feature modules + `features:install` / `auth:setup`. Both commands are passphrase-gated — **never invent or brute-force the passphrase; ask the user to run it.** |
| 3 | [RULES.md](.claude/context/RULES.md) | **Overrides everything, including user shortcuts like "just make it work."** Naming, SOLID/DRY/KISS, the layer law, security, performance, testing gate, banned list. |
| 4 | [ARCHITECTURE.md](.claude/context/ARCHITECTURE.md) | Where code lives, layer contracts, routing, idempotency, frontend architecture. |
| 5 | [SCHEMA.md](.claude/context/SCHEMA.md) | DB conventions (Part A) + **living schema (Part B) — update it in the same change as any migration.** |
| 6 | [DESIGN.md](.claude/context/DESIGN.md) | Tokens, components, forms, a11y, RTL, perf budget, UI Definition of Done. |
| 7 | [PRD.md](.claude/context/PRD.md) | What THIS project is. **No ghost features** — if it isn't in the PRD, add it there first. |

**Conflict protocol:** if a request conflicts with RULES.md, say so in one sentence, propose
the compliant alternative, then implement the compliant version. Never silently ship
non-compliant code.

---

## 2. What this project is

A **CRM-side resume ingestion pipeline**: manage client **companies** (each with a logo and
its own resume house-style), upload a candidate's PDF resume against a chosen company, and
automatically produce an **ATS-safe resume rendered in that company's format**.

```
Company CRUD (logo, brand, template)
        |
        +-- pick a company -> upload candidate PDF
                    |
                    v
          Laravel stores the file (private disk)
                    |  queued job
                    v
          Python sidecar  POST /v1/parse   <- text extraction + section/entity parsing
                    |  structured JSON
                    v
          AtsResumeFormatter -> company template + section order
                    |
                    v
          ATS resume preview (HTML, print/PDF-ready)
```

### Stack (as actually installed — supersedes RULES §1 version numbers)

| Layer | RULES.md says | **This repo actually runs** |
|---|---|---|
| PHP | 8.4 | **8.4.14** ✔ |
| Laravel | 12.x | **13.20** (Laravel React starter kit) |
| Inertia | 2 | **inertia-laravel 3 / @inertiajs/react 3** |
| React | 19 | **19.2** ✔ |
| Auth | Breeze | **Fortify 1.37 + `@laravel/passkeys`** (`auth:setup` targets Breeze/Blade — do **not** run it here) |
| Routing helpers | — | **Wayfinder** (`@laravel/vite-plugin-wayfinder`) generates `resources/js/routes/*` + `resources/js/actions/*` |
| Tests | Pest 3 | **PHPUnit 12** (`tests/Feature`, `tests/Unit`, `Tests\TestCase`) |
| DB | MySQL 8.4 / PG 17 | **SQLite** (`database/database.sqlite`) — see §3 |
| Tailwind | 4 | **4.1** ✔ · shadcn primitives in `resources/js/components/ui/` |
| Sidecar | — | **Python 3.12 + FastAPI** in [sidecar/](sidecar/) |

Stack gate is resolved and persisted: `APP_STACK=react` in `.env`, `config/features.php`
`stack`. **Never re-scaffold Inertia/React** — it is already wired.

---

## 3. Documented deviations from the context boilerplate

These are decided, not accidental. Follow the **right-hand column**; do not "fix" them back
toward the boilerplate without asking.

| RULES/ARCH says | This repo does | Why |
|---|---|---|
| Inertia pages `PascalCase.tsx` | **kebab-case** (`pages/companies/index.tsx`) | Starter-kit + Wayfinder convention already in place; mixing cases breaks resolution. |
| Pest feature tests | **PHPUnit** class tests, `test_snake_case()` methods | No Pest installed; matches `tests/Feature/DashboardTest.php`. |
| Tests on MySQL/PG, never SQLite | **SQLite `:memory:`** (`phpunit.xml`) | No MySQL provisioned yet. **Accepted temporary deviation** — migrations stay engine-agnostic and uniqueness is enforced by real UNIQUE constraints, so the guarantee survives the move. Flip to MySQL the moment credentials exist (RULES §7). |
| `components/devio/` shared kit | **`components/crm/`** for project composites | Single-project repo; graduate anything reused across repos into `devio/`. |
| Breeze via `auth:setup` | **Fortify (already installed)** | Starter kit ships Fortify + passkeys + 2FA. |
| Bilingual EN/AR + RTL (RULES §9.1) | **Not required** — internal CRM tool, EN only | Still use logical properties (`ms-*`/`me-*`) so RTL stays cheap later. |
| `declare(strict_types=1)` everywhere | **Required in all new files**; pre-existing starter-kit files lack it | Don't mass-edit starter files; new code complies. |

---

## 4. Domain map — where the feature code lives

```
app/
├── Actions/Company/          CreateCompany · UpdateCompany · DeleteCompany
├── Actions/Resume/           StoreResume · ParseResume
├── Contracts/                ResumeParser  <- the sidecar boundary (RULES §3-D)
├── DTOs/                     CompanyData · ResumeUploadData · ParsedResume + child DTOs
├── Enums/                    ResumeStatus · ResumeTemplate
├── Exceptions/               ResumeParsingFailedException
├── Http/Controllers/         CompanyController · ResumeController · ResumeFileController
├── Http/Requests/Company|Resume/
├── Http/Resources/           CompanyResource · CompanyCardResource · ResumeResource · ResumeCardResource
├── Jobs/                     ParseResumeJob        (queued; never parse in-request)
├── Models/                   Company · Resume
├── Policies/                 CompanyPolicy · ResumePolicy
└── Services/                 Ats/AtsResumeFormatter · Parsing/SidecarResumeParser · Parsing/FakeResumeParser

resources/js/
├── components/crm/           company-form · company-logo-input · resume-upload-card
│                             ats-resume-preview · resume-status-badge · empty-state
├── pages/companies/          index · create · edit · show
├── pages/resumes/            show          (the ATS output)
└── types/models.ts           mirrors the API Resources 1:1

sidecar/                      FastAPI service — see sidecar/README.md
routes/crm.php                all company + resume routes (auth + verified)
```

### The parsing boundary (read before touching parsing)

`App\Contracts\ResumeParser` is the **only** way Laravel talks to Python.

- Production binding → `SidecarResumeParser` (HTTP to `config('services.sidecar.url')`,
  bearer token, timeout + retries).
- Tests / local-without-Python → `FakeResumeParser` (deterministic fixture).
- Binding lives in `AppServiceProvider`, switched by `config('services.sidecar.driver')`.

**Never** call the sidecar from a controller, a model, or React. **Never** `new` the parser
inside a method. **Never** parse synchronously in a request — it goes through
`ParseResumeJob`.

---

## 5. Commands you will actually use

```bash
composer dev            # artisan dev -> server + queue + vite (the one you want)
composer lint           # Pint (PSR-12)          --+
composer types:check    # PHPStan/Larastan         +-- all three pass = "done"
npm run types:check     # tsc --noEmit             |
npm run lint:check      # ESLint                 --+
composer test           # config:clear + lint + phpstan + artisan test
php artisan test --filter=Company
php artisan migrate                 # SQLite file, safe to re-run
php artisan db:seed                 # realistic demo companies + resumes (RULES §9.3)
php artisan storage:link            # required once, for company logos
php artisan pail                    # live logs

# sidecar (separate process, port 8001)
cd sidecar && python -m venv .venv && .venv/Scripts/pip install -r requirements.txt
.venv/Scripts/python -m uvicorn app.main:app --reload --port 8001
.venv/Scripts/python -m pytest
```

Wayfinder route helpers regenerate on `vite` / `npm run build`. If a `@/routes/...` import is
missing, run the dev server (or `php artisan wayfinder:generate`) — never hand-write route
strings in TSX.

### The dev server is your compiler — always read it

**`npm run dev` (Vite) runs in the background and it is the fastest error signal in this
repo. After every frontend edit, read its output before doing anything else.**

- Keep one long-lived background `npm run dev` (or `composer dev`, which wraps server +
  queue + vite). Do **not** start a second one — the port collision hides real errors.
- After touching any `.tsx` / `.ts` / `.css`: check the dev-server output. Vite names the
  exact file and line for import errors, JSX syntax errors, missing Wayfinder routes and
  Tailwind issues. **Fix what it reports immediately, before writing the next file** — do
  not batch up frontend errors.
- A silent HMR update means the module compiled. `[vite] Internal server error`,
  `Failed to resolve import`, or a `500` on `/build/...` means it did not — that file is
  broken right now.
- Vite catches resolution and syntax errors, **not** type errors. `npm run types:check`
  (tsc) is still required before calling a change done.
- If the dev server output is stale or empty, restart it once and re-read; if a route helper
  is missing, that is Wayfinder — save a PHP route file to trigger regeneration.

Same discipline server-side: keep `php artisan pail` handy for Laravel-side exceptions rather
than guessing from a browser error page.

### Long-lived processes serve STALE code and config — restart them

This has already caused two "impossible" bugs (a real upload rendering sample data; a fixed
parser still returning old output). Three processes cache the app at boot:

| Process | Caches | Restart after |
|---|---|---|
| `php artisan queue:work` | **all PHP code + `.env`** | any code or `.env` change → `php artisan queue:restart` |
| `uvicorn` (sidecar) | Python modules | any `sidecar/**.py` change → use `--reload` |
| `php artisan serve` / vite SSR | compiled views/SSR bundle | usually self-reloads; restart if output looks impossible |

Rules of thumb:

- **A queue worker never sees a config change.** After editing `.env` (especially
  `SIDECAR_DRIVER`), run `php artisan queue:restart` or the job keeps using the old value.
- Run the sidecar with `--reload` in development.
- When a result contradicts the code you just wrote, **suspect a stale process before
  suspecting the logic** — verify by running the same thing in a fresh process
  (`php artisan tinker --execute=…`, or curl the sidecar directly). If fresh differs from the
  worker, it's staleness.
- `bootstrap/cache/config.php` (from `config:cache`) freezes `.env` for *every* process —
  `php artisan config:clear` in development.

### Tooling not yet installed (SETUP.md §1–§6 still pending)

Debugbar, Telescope, IDE Helper and Spatie Permission are **not installed**. Larastan and
Pail are. Do not assume `role:` / `permission:` middleware exists — authorization is
**Policies + `$this->authorize()`**, and `config('features.spatie')` is `false`, so
`AppServiceProvider` runs the `Gate::before(fn () => true)` bypass. **Because of that bypass a
Policy that isn't also covered by a direct test proves nothing** — treat RULES §5.1 as
unfinished until `ENABLE_SPATIE=true`.

---

## 6. Standing constraints for this codebase

1. **Uploads** (RULES §5.7) — logos and resumes: validate mime + size server-side, random
   filenames, **resumes on the private `local` disk** (never public), logos on `public`.
   Serve resume files only through an authorized controller route.
2. **PII** — resumes are PII-dense (name, email, phone, employment history). Tag every PII
   column in SCHEMA.md Part B, never log parsed contact data, scrub log context.
3. **Idempotency** (RULES §5.5) — re-uploading the same file for the same company must not
   create a duplicate: `UNIQUE(company_id, file_hash)`; the Action catches
   `UniqueConstraintViolationException` and returns the existing resume, not a 500.
4. **N+1 is a build failure** — `Model::preventLazyLoading()` is on outside production.
   Eager-load `company` on resume lists; use `*CardResource` for index pages.
5. **Every route named, every route authorized**; mutations go through a Policy.
6. **SCHEMA.md Part B and PRD.md are living documents** — update them in the same change as
   the migration/feature. A migration whose table isn't in Part B is not done.
7. **Banned** (RULES §11) — `$request->all()`, `$guarded = []`, `env()` outside config,
   `DB::` in controllers, `console.log` / `dd()` in committed code, `any` / `@ts-ignore`,
   business logic in pages or models.

---

## 7. Leave no junk behind (run before reporting done)

Every task ends with a cleanup pass. **Nothing temporary survives the task that created it.**

Delete:

- Scratch scripts, one-off `test-*.php` / `check_*.py` / `tmp*.ts` probes, `*.bak`, `*.orig`,
  `*.rej`, `*.log` you produced, editor leftovers.
- Debug output: `dd()` / `dump()` / `var_dump()` / `ray()` / `console.log` / `print()`, and
  any temporary route, seeder, command or fixture added just to eyeball something.
- Dead code from the change: commented-out blocks, unused imports, unused types, orphaned
  components/files a refactor left stranded, duplicated helpers you replaced.
- Generated artefacts that don't belong in git (`_ide_helper*.php`, coverage output, Python
  `__pycache__` / `.pytest_cache`, stray `storage/app/**` test uploads).

Rules:

- **Throwaway files go in the scratchpad directory, never in the repo** — then there is
  nothing to clean up.
- If something looks like junk but might be intentional (a real fixture, a committed sample
  PDF), **ask before deleting** — cleanup never removes the user's own files.
- Verify with `git status --porcelain` before saying done: every remaining untracked or
  modified file must be one you meant to add. Confirm the suite is still green after removing
  anything.

---

## 8. Definition of done (every change)

- [ ] Layer law respected: FormRequest validates → Controller orchestrates → Action works in
      a transaction → Resource shapes output.
- [ ] Policy + named route + middleware group.
- [ ] Migration ⇄ SCHEMA.md Part B in sync; feature ⇄ PRD.md in sync.
- [ ] Tests: happy path + one failure path per Action; 403 per Policy; duplicate-rejected per
      uniqueness rule.
- [ ] `composer test` and `npm run lint:check && npm run types:check` clean.
- [ ] UI: all 5 states (loading / empty / error / success / forbidden), 360px→1440px, dark
      mode, keyboard pass (DESIGN §10).
- [ ] Dev-server output read and clean (§5) — no unresolved Vite errors.
- [ ] Cleanup pass done (§7): `git status --porcelain` shows only intended files.
