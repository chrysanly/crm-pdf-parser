# DESIGN.md — devio Frontend & UI Standards

> Read after SCHEMA.md. Governs everything the user sees. React 19 + Inertia 2 + Tailwind 4 + shadcn/ui.

---

## 1. Design principles

1. **Fast is a feature.** Perceived speed beats visual flourish: optimistic UI where safe, skeletons over spinners, prefetch likely next pages on hover.
2. **Every state designed.** A view isn't done until it has: loading (skeleton), empty (illustration + CTA), error (retry path), success (flash), and forbidden states.
3. **Bilingual-first.** EN/AR from day one where PRD requires it; RTL is a layout mode, not an afterthought.
4. **Server is the source of truth.** UI never computes prices, stock, permissions, or availability — it renders what Resources send.

## 2. Design tokens (Tailwind 4 `@theme`)

- Single `resources/css/app.css` defines: brand palette (primary/surface/success/warning/danger scales), radius scale, spacing rhythm (4px base), font stack (Latin + Arabic: e.g. Inter + IBM Plex Sans Arabic), shadow scale.
- **No hex codes in components.** Only token classes (`bg-primary-600`). New color = new token, PR-reviewed.
- Dark mode via `class` strategy; every token has a dark value; test both modes before merge.

## 3. Component rules

- Build on `components/ui` (shadcn) primitives; never restyle a primitive inline — wrap it in `components/devio/` with variants (CVA).
- **devio-ui kit (reuse across sites):** the cross-project brand kit in `components/devio/` — **Navbar, Hero, PricingCard, ContactForm, Footer, RTL wrapper** — is built once from the first demo and **copied forward**. Compose new pages from it; never re-implement a shared primitive per site (DRY across the portfolio, RULES §9). Anything reused on ≥ 2 sites graduates into this kit.
- Props typed, no `any`; variants over boolean prop explosions (`variant="danger"` not `isDanger isOutline isSmall`).
- Composition over configuration: `<Card><Card.Header/>…` beats a 15-prop mega component.
- One component = one file = one responsibility (SRP applies to React too). Extract when a component exceeds ~150 lines or two levels of conditional rendering.
- All logic in hooks (`useCart`, `useFilters`, `useBookingStepper`); components render.

## 4. Forms (the standard flow)

- `useForm` from Inertia only. Server errors are the gate; inline client checks are UX sugar.
- Submit buttons: `disabled={processing}` + spinner — this plus the Idempotency-Key (ARCHITECTURE §6) is the double-submit guard.
- Errors render adjacent to fields (`aria-describedby`), first error auto-focused, form-level error summary for >5 fields.
- Destructive actions: confirmation dialog naming the object ("Delete listing 'Marina Loft'?"), never window.confirm.
- Money/phone inputs: masked/formatted display, raw normalized value submitted.

## 5. Data display

- Lists: paginated (server) with preserved scroll/state on filter change (Inertia partial reloads). URL always reflects filter state (shareable, back-button safe).
- Tables: sortable headers hit server sort params; loading rows keep column widths (no layout shift); mobile = card collapse, not horizontal scroll.
- Numbers: `Intl.NumberFormat('en-AE')` / `('ar-AE')`; currency AED with correct locale placement; dates via `Intl.DateTimeFormat`, timezone Asia/Dubai from config.
- Status everywhere via one `<StatusBadge status={…}/>` mapping enum → token color/label (labels come from the enum's `label()` through the Resource — no frontend string maps).

## 6. Accessibility (merge-blocking)

- Interactive = focusable: real `<button>/<a>`, visible focus rings (`focus-visible`), logical tab order.
- Color contrast AA (4.5:1 body, 3:1 large). Never color-only meaning (badge = color + text).
- Images: meaningful `alt`; decorative `alt=""`. Form fields: real `<label>`.
- Dialogs/menus: shadcn primitives already handle focus trap + ESC — don't hand-roll replacements.

## 7. RTL / i18n implementation

- `dir` and `lang` set on `<html>` from shared Inertia prop.
- Use logical properties/utilities (`ms-*`, `me-*`, `ps-*`, `text-start`) — never `ml-*/mr-*` in shared components.
- Icons implying direction (arrows, chevrons) flip via `rtl:rotate-180`.
- Copy from a translation layer (server `__()` passed through Resources/shared props). No hardcoded UI strings in components — extract to lang files.

## 8. Frontend performance budget

- Initial JS < 200KB gz per page; heavy widgets (charts, maps, editors) `React.lazy` + suspense skeleton.
- Images: responsive `srcset` sizes generated server-side (media library conversions), `loading="lazy"` below fold, explicit width/height (CLS = 0 target).
- Fonts: self-hosted, `font-display: swap`, subsets (latin + arabic split).
- Lighthouse gates on key pages before deploy: Performance ≥ 90, A11y ≥ 95, SEO ≥ 95.
- SSR enabled for public marketing/catalog pages (SEO); admin/dashboard client-only is fine.

## 9. UX writing

- Buttons = verb + object ("Save listing", not "Submit"). Errors say what happened + what to do next. Empty states sell the action ("No bookings yet — share your booking link").
- Confirmation copy in the user's language, mirroring AR/EN tone. No developer jargon in user-facing errors (map domain exceptions to human copy).

## 10. Definition of Done (any UI task)

- [ ] All 5 states implemented (loading/empty/error/success/forbidden)
- [ ] Mobile 360px → desktop 1440px verified
- [ ] Dark mode verified
- [ ] RTL verified (if bilingual project)
- [ ] Keyboard-only pass completed
- [ ] No console errors/warnings; tsc + ESLint clean
- [ ] Matches tokens (no raw hex/px magic numbers)
