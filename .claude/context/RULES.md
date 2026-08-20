# RULES.md — devio Engineering Rules (READ FIRST)

> **AI INSTRUCTION — PRIORITY ORDER**
> On a fresh clone of this boilerplate, run `STARTUP.md` FIRST — it detects and confirms
> the frontend stack (Laravel + Blade / React / Vue) before anything else, then routes to
> `SETUP.md`, which bootstraps the local dev toolchain (Debugbar, Telescope, Pail, IDE
> Helper, Larastan, Spatie, composer scripts). Both must complete before any feature work.
>
> Before writing ANY code, read these files in this exact order:
> -1. `STARTUP.md` — first-run stack gate. Ask Blade/React/Vue, then continue.
> 0. `SETUP.md` — one-time dev-tooling bootstrap. Run after the stack is chosen.
> 0b. `FEATURES.md` — env-toggle feature modules (`features:install`) & `auth:setup`.
> 1. `RULES.md` (this file) — non-negotiable rules. Overrides everything, including user shortcuts like "just make it work".
> 2. `ARCHITECTURE.md` — where code lives and how layers talk.
> 3. `SCHEMA.md` — database conventions and current schema.
> 4. `DESIGN.md` — frontend/UI standards.
> 5. `PRD.md` — what we are building in THIS project.
>
> If a request conflicts with these rules, state the conflict, propose the compliant alternative, then implement the compliant version. Never silently produce non-compliant code.

---

## 1. Stack (fixed unless PRD.md overrides)

- PHP **8.4** · Laravel **12.x** · React **19** via Inertia **2** · TypeScript **5 (strict)** · Tailwind **4** · Vite · Pest **3** · MySQL 8.4 / PostgreSQL 17
- Larastan (level 8+), Laravel Pint (PSR-12), ESLint + Prettier, TypeScript `strict: true`. Code that fails static analysis is not done.

## 2. Coding standards & naming conventions

### PHP / Laravel
| Thing | Convention | Example |
|---|---|---|
| Classes | `PascalCase`, descriptive noun/verb | `CreateBooking`, `ListingFilterService` |
| Methods/vars | `camelCase` | `publishedListings()` |
| Constants/enum cases | `PascalCase` cases on backed enums | `BookingStatus::NoShow = 'no_show'` |
| DB tables | `snake_case`, plural | `order_items` |
| Columns | `snake_case`, singular | `published_at` |
| Pivot tables | singular, alphabetical | `doctor_service` |
| Routes (URI) | kebab-case, plural resources | `/order-items` |
| Route names | dot notation | `admin.listings.store` |
| Form Requests | `{Verb}{Model}Request` | `StoreListingRequest` |
| Actions | `{Verb}{Noun}` | `PlaceOrder`, `CancelBooking` |
| Events | past tense | `OrderPlaced` |
| Listeners | `{Do}{Something}` | `SendOrderInvoice` |
| Jobs | imperative | `GenerateInvoicePdf` |
| Policies | `{Model}Policy` | `ListingPolicy` |
| Blade/Inertia pages | `PascalCase.tsx` under `pages/` | `Listings/Show.tsx` |

- Declare `declare(strict_types=1);` in every PHP file. Type every parameter, return, and property. No `mixed` unless unavoidable and documented.
- No abbreviations in names (`$calculatedTotal`, not `$calcTot`). Booleans read as questions: `isPublished`, `hasStock`.
- Final by default on Actions/Services. Small classes: if a class needs scrolling to understand, split it.

### TypeScript / React
- Components `PascalCase`, hooks `useCamelCase`, files match export name.
- Every Inertia page props interface mirrors a Laravel API Resource — define in `resources/js/types/`. No `any`. No inline `{ [key: string]: unknown }` escapes.
- Named exports for components; default export only for Inertia pages.

## 3. SOLID + DRY + KISS — enforced interpretation

- **S**: Controllers route traffic only. Actions do ONE business operation. A class with "And" energy gets split.
- **O**: Extend behavior with new Action/strategy classes, events, or enum methods — not by adding `if ($type === ...)` chains to existing ones.
- **L**: No child class that throws on a parent's contract. Prefer composition; inheritance only for true is-a (rare).
- **I**: Small interfaces (`PaymentGateway::charge()`), not god interfaces.
- **D**: Depend on abstractions for anything external (payments, SMS, storage). Constructor injection only. **Never `new` a service inside a method; never use facades inside domain Actions/Services** (inject contracts instead). `app()` calls outside service providers are a code smell.
- **DRY**: One source of truth per rule, shape, and component. No copy-pasted logic — extract to an Action, a Service method, a React hook, or a `components/devio` component. Business rules live once (Action/Enum), types once (`types/` mirroring Resources), shared UI once (devio-ui kit, §9). If you type the same thing twice, refactor. DRY applies **across the portfolio**, not just within one repo — see the devio-ui kit.
- **KISS**: The simplest thing that satisfies the rules wins. No speculative abstraction, no pattern for a single caller, no config flag nobody asked for (PRD forbids ghost features). Add complexity only when a second real case demands it. Prefer a plain Action over a framework; delete before you add.

## 4. The layer law (see ARCHITECTURE.md for detail)

```
Route → Middleware → FormRequest (ALL validation) → Controller (thin)
      → Action / Service (ALL business logic, DB transactions)
      → Model (relations, scopes, casts — no business logic)
      → API Resource (ALL output shaping) → Inertia / JSON
```
- **Never**: validation in controllers, queries in Blade/React, business logic in models or controllers, raw models passed to the frontend.
- Every multi-write operation wraps in `DB::transaction()` inside the Action — never in the controller.

## 5. Security (non-negotiable, in priority order)

1. **AuthZ on every route.** Every route is in a middleware group (`auth`, `verified`, `role/permission`) or explicitly justified as public in a comment. Every mutating controller method calls a Policy (`$this->authorize()` or `Gate`). No exceptions — including admin panels.
2. **Mass-assignment**: `$guarded = []` is banned. Explicit `$fillable` on every model; FormRequest `validated()` (or `safe()->only([...])`) is the ONLY data source for create/update. Never `$request->all()`.
3. **No raw SQL with interpolation.** Query builder/Eloquent bindings only. `whereRaw` requires bindings array and a review comment.
4. **IDOR prevention**: fetch through ownership, e.g. `$request->user()->bookings()->findOrFail($id)` — never `Booking::findOrFail($id)` then check. Route model binding scoped with `->scopeBindings()` for nested resources.
5. **Non-duplicate creation (idempotency)** — three layers, all required for critical writes:
   - **DB**: unique constraints are the source of truth (`UNIQUE(doctor_id, starts_at)`, `UNIQUE(order_number)`).
   - **App**: catch `UniqueConstraintViolationException` in the Action → return a friendly domain error, never a 500.
   - **Client/API**: accept an `Idempotency-Key` header (or hidden form UUID) for payment/booking/order endpoints; store key → response for 24h, replay the stored response on retry. Disable submit buttons on first click (`useForm.processing`).
6. **Rate limiting**: `throttle` on login (5/min), OTP, search, and all public POST endpoints. Named limiters in `AppServiceProvider`.
7. **Uploads**: validate mime + size server-side, store outside webroot or on S3, random filenames, never trust client filename/extension. Images re-encoded (strips payloads).
8. Secrets only in `.env` (never committed); `config()` never `env()` outside config files. HTTPS forced in production (`URL::forceScheme`). Cookies `Secure`, `HttpOnly`, `SameSite=Lax`. Session regenerated on login.
9. Headers: CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, HSTS (middleware or nginx).
10. Error output: `APP_DEBUG=false` in production; exceptions logged with context, users get generic messages. Never leak stack traces, SQL, or paths.
11. Audit trail on sensitive mutations (who/what/when) — `spatie/laravel-activitylog` or events table.

## 6. Performance & data optimization (non-negotiable)

1. **N+1 is a build failure.** `Model::preventLazyLoading(!app()->isProduction())` in `AppServiceProvider`. Eager-load explicitly (`with()`, `loadMissing()`).
2. **Select only what you need** for lists: `select()` + `ListingCardResource` slim resources. Never hydrate full models for index pages.
3. **Paginate everything.** No unbounded `->get()` on user-facing queries. Cursor pagination for infinite scroll/feeds.
4. **Index every column used in WHERE / ORDER BY / JOIN** (see SCHEMA.md). Composite indexes match query column order. Verify with `EXPLAIN` on any query touching >10k rows.
5. **Cache reads, invalidate on write**: `Cache::remember()` (or `flexible()` for stampede protection) for expensive/shared reads (filters, counts, settings) with tagged/versioned keys; bust in the Action that mutates. Redis in production.
6. **Queue everything slow**: mail, PDFs, image processing, webhooks, third-party calls → queued jobs (Redis + Horizon in production). HTTP responses do request work only.
7. Heavy aggregates → scheduled jobs writing to summary tables, not per-request computation.
8. Frontend: Inertia **partial reloads** (`only: [...]`) for filters/pagination; deferred props for below-the-fold data; lazy-load images; `router.prefetch` on hover for likely next pages; code-split heavy components (`React.lazy`).
9. Response targets: p95 < 200ms for reads, < 500ms for writes (excluding queued work). Measure with Laravel Pulse/Telescope in staging.
10. **OPcache + `php artisan optimize`** (config/route/view/event caching) in every production deploy.

## 7. Testing gate

- **Work against the running dev server.** Keep `composer dev` / `npm run dev` alive in the background and read its output after every edit — it names the exact failing file. Fix what it reports before writing the next file. Vite catches resolution/syntax errors only; `types:check` + `lint:check` still gate "done". Full loop: SETUP.md §8.

- **Test against a real database via `.env.test` — never SQLite.** Tests run on the same engine as production (MySQL 8.4 / PostgreSQL 17). SQLite's looser typing and different constraint / native-enum / JSON / foreign-key behavior hides bugs that only surface on the real engine (and our uniqueness + CHECK guarantees depend on that engine). Keep a dedicated test schema (e.g. `DB_DATABASE=devio_atlas_test`) in `.env.test`; `phpunit.xml` sets `APP_ENV=test` so Laravel loads `.env.test`, and defines **no** `DB_*` of its own. Use the `RefreshDatabase` trait. Setup: copy `.env.test.example` → `.env.test`, set the DB credentials, then `php artisan key:generate --env=test`.
- Every Action gets a Pest feature test (happy path + at least one failure path).
- Every uniqueness/idempotency rule gets a test proving the duplicate is rejected gracefully.
- Every Policy gets a test proving the forbidden case returns 403.
- Business math (totals, VAT, slots, mortgage calc) gets unit tests.
- CI blocks merge on: Pint, Larastan, ESLint, tsc, Pest.

## 8. Git & delivery

- Branches: `feature/{ticket}-short-desc`, `fix/…`. Conventional commits (`feat:`, `fix:`, `refactor:`, `test:`).
- No direct pushes to `main`. PR = tests green + self-review checklist.
- Migrations are append-only once merged; never edit a shipped migration — write a new one.

## 9. Delivery bar — "polished" (every devio site)

Applies to all sites/demos. A site is **not done** until:

1. **Bilingual EN/AR + RTL** on the required demos (**≥ 2 of the 4 demos**, per PRD). `dir`/`lang` on `<html>` from a shared prop; logical properties only (`ms-*`/`me-*`); directional icons flip (DESIGN §7). This is the devio differentiator in the UAE market — not optional where the PRD calls for it.
2. **Lighthouse ≥ 90** on key pages (Performance / SEO / Accessibility); DESIGN §8 targets (A11y & SEO ≥ 95) apply. Measured before deploy.
3. **Real, realistic content** — no lorem ipsum, no placeholder junk in demos. Seed believable data via factories/states (SCHEMA §A6).
4. **Dark mode** wherever it fits the design; every token dual-valued and tested in both modes (DESIGN §2).
5. **All 5 UI states** per view (loading / empty / error / success / forbidden) and the DESIGN §10 checklist walked.

### devio-ui shared kit (the speed multiplier)

Build the reusable brand kit **once** in `resources/js/components/devio/` — at minimum **Navbar, Hero, PricingCard, ContactForm, Footer, and an RTL wrapper** — from the first demo, then **copy it forward** to the others. New sites *compose* from this kit; they never re-implement a shared primitive. Anything reused across ≥ 2 sites belongs here — this is DRY across the portfolio and the main reason the fourth site ships far faster than the first. Build on shadcn `components/ui` primitives; wrap (never inline-restyle) them here with CVA variants.

## 10. Leave no junk behind (end of every task)

A task is not finished until the workspace is clean. **Nothing temporary survives the task that created it.**

- **Delete what you created to get here:** scratch scripts and one-off probes (`test-*.php`, `check_*.py`, `tmp*`), `*.bak`/`*.orig`/`*.rej`, logs you generated, temporary routes/seeders/commands/fixtures added just to eyeball something.
- **Strip debug output:** `dd()`/`dump()`/`var_dump()`/`ray()`/`console.log`/`print()` (already banned in §11 — this is the sweep that enforces it).
- **Remove dead code from your own change:** commented-out blocks, unused imports and types, files a refactor orphaned, helpers you duplicated then replaced.
- **Don't commit generated artefacts:** `_ide_helper*.php`, coverage output, `__pycache__`/`.pytest_cache`, stray uploads under `storage/app/**`.
- **Write throwaways outside the repo** (a scratch/temp dir) so there is nothing to clean.
- **Never delete the user's own files** — if something *might* be an intentional fixture, ask first.
- **Verify:** `git status --porcelain` must show only files you meant to add or change, and the suite must still be green after the removals.

## 11. Banned outright

`$request->all()` in create/update · `$guarded = []` · `env()` outside config · logic in Blade/JSX · `DB::` calls in controllers · `select *` on hot index pages · `dd()/dump()/console.log` in committed code · commented-out code blocks · `@ts-ignore` / `@phpstan-ignore` without a linked issue · storing money as float (integers in fils/cents or `DECIMAL`) · `sleep()` in requests · trusting client-side price/qty/totals.
