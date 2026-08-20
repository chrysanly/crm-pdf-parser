# STARTUP.md — First-Run Gate (READ THIS BEFORE ANYTHING ELSE)

> **AI INSTRUCTION — RUN FIRST, ALWAYS, in every project that carries this context.**
> This file runs **before `SETUP.md`, `RULES.md`, and all other context files.**
> Do not scaffold, install, or write any code until this gate is resolved.

---

## 0. What this gate does

This context ships as a portable boilerplate. Before doing anything, you must know which
**frontend stack** the current project uses, because the setup and conventions differ:

1. **Laravel + Blade** — server-rendered Blade views + Tailwind (no SPA).
2. **Laravel + React** — Inertia 2 + React 19 + TypeScript.
3. **Laravel + Vue** — Inertia 2 + Vue 3 + TypeScript.

## 1. Detect, then ASK (never assume)

**Step 1 — detect a likely stack** from the repo (evidence only, do not act on it yet):

| Signal | Points to |
|---|---|
| `@inertiajs/react` in `package.json`, `resources/js/pages/*.tsx` | Laravel + React |
| `@inertiajs/vue3` in `package.json`, `resources/js/pages/*.vue` | Laravel + Vue |
| `resources/views/*.blade.php` as the primary UI, no Inertia dep | Laravel + Blade |
| No `package.json` / fresh skeleton | ask, no default |

Run a quick check (adjust to the OS):

```bash
cat composer.json package.json 2>/dev/null | grep -iE "inertia|@inertiajs|vue|react" || echo "no SPA deps found"
ls resources/js 2>/dev/null; ls resources/views 2>/dev/null
```

**Step 2 — ASK the user to confirm**, presenting your detected guess first:

> "This project looks like **Laravel + Blade** (detected: Blade views, no Inertia).
> Which stack should I set this up as?
> 1. Laravel + Blade  2. Laravel + React  3. Laravel + Vue"

Always ask even when detection is confident — the user has the final say.

## 2. Record the choice (so the gate only runs once)

Once confirmed, persist it so future sessions skip the question. Write the answer to
`config/features.php` under a `stack` key **and** note it at the top of `PRD.md`:

```php
// config/features.php
'stack' => env('APP_STACK', 'blade'), // 'blade' | 'react' | 'vue'
```

```env
# .env
APP_STACK=blade
```

If `APP_STACK` (or the `PRD.md` stack line) is already set, **skip the question** and
proceed straight to §3 with that stack.

## 3. Route to the matching setup

After the stack is known:

1. **Always run `SETUP.md`** — the dev-tooling bootstrap (Debugbar, Telescope, Pail,
   IDE Helper, Larastan, Spatie, composer scripts). It is stack-agnostic and applies to
   all three.
2. **Then apply the stack-specific frontend delta** below.
3. **Then** continue to `RULES.md` → `ARCHITECTURE.md` → `SCHEMA.md` → `DESIGN.md` → `PRD.md`.

### 3a. Laravel + Blade  ← this repository's default

- UI = Blade + Tailwind, server-rendered. No Inertia, no SPA build beyond Vite for
  CSS/JS assets.
- `resources/views/*.blade.php` is the source of truth for pages; components via Blade
  components / `@include`.
- Keep the existing `welcome.blade.php` boilerplate landing.

### 3b. Laravel + React

- `composer require inertiajs/inertia-laravel && php artisan inertia:middleware`
- `npm i @inertiajs/react react react-dom` + TypeScript, `@vitejs/plugin-react`.
- Pages in `resources/js/pages/*.tsx`; root Blade `app.blade.php` with `@inertia`.
- Every page props interface mirrors an API Resource in `resources/js/types/` (RULES §2).

### 3c. Laravel + Vue

- `composer require inertiajs/inertia-laravel && php artisan inertia:middleware`
- `npm i @inertiajs/vue3 vue` + TypeScript, `@vitejs/plugin-vue`.
- Pages in `resources/js/pages/*.vue`; root Blade `app.blade.php` with `@inertia`.
- Same typed-props-mirror-Resource rule as React.

> For React/Vue, also update `welcome.blade.php` (or replace it with an Inertia landing)
> so the "Laravel + Blade" wording reflects the chosen stack.

---

## Summary flow

```
STARTUP.md (this file)
  ├─ detect stack from repo
  ├─ ASK user to confirm (Blade / React / Vue)   ← unless APP_STACK already set
  ├─ persist choice (config/features.php + .env + PRD.md)
  └─ SETUP.md (shared dev tooling) → stack delta → RULES → ARCHITECTURE → SCHEMA → DESIGN → PRD
```
