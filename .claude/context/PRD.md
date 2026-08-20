# PRD.md — Product Requirements (TEMPLATE — fill per project)

> **Stack (resolved by STARTUP.md gate):** Laravel + React (Inertia 2 + React 19 + TypeScript).
> Persisted in `.env` (`APP_STACK=react`) and `config/features.php` (`stack`). The first-run
> gate is satisfied — do not ask the stack question again for this project.

> Read LAST, build FIRST from this. RULES/ARCHITECTURE/SCHEMA/DESIGN say HOW; this file says WHAT and WHY.
> AI: if a requested feature is not in this file, ask or add it here first — no ghost features.
> Anything marked `[DECIDE]` blocks related implementation until resolved.

---

## 1. Overview
- **Project name / codename:**
- **One-liner:** (e.g. "Booking website for a Dubai dental clinic with EN/AR support.")
- **Client / owner:**
- **Business goal (measurable):** (e.g. "30 online bookings/month within 60 days of launch.")
- **Target launch date:**  **Environment domains:** staging: · production:

## 2. Users & roles
| Role | Description | Can | Cannot |
|---|---|---|---|
| Guest | | browse public pages | |
| Customer | registered user | | see other users' data |
| Staff | | | manage settings/users |
| Admin | | everything in panel | |

Authorization matrix drives Policies — every "Can/Cannot" cell becomes a Policy method + test.

## 3. Scope

### In scope (v1) — ranked
| # | Feature | User story | Priority | Acceptance criteria |
|---|---|---|---|---|
| 1 | | As a …, I want …, so that … | Must | Given/When/Then, incl. failure case |
| 2 | | | Must | |
| 3 | | | Should | |

### Explicitly OUT of scope (v1)
- (List everything cut. AI must refuse scope creep politely and point here.)

## 4. Domain rules (business logic source of truth)
> Every rule here becomes an Action/Service + a Pest test. Number them; reference numbers in code comments.

- **BR-1:** (e.g. "A doctor cannot have two bookings overlapping — enforced by UNIQUE(doctor_id, starts_at) + SlotGenerator.")
- **BR-2:** (e.g. "Orders include 5% UAE VAT, computed server-side, line-item rounded half-up.")
- **BR-3:** (e.g. "Cancellations allowed ≥24h before start; later = admin only.")
- **BR-…:**

## 5. Non-duplicate / idempotency map
| Operation | DB constraint | Idempotency-Key required? | On duplicate |
|---|---|---|---|
| Create booking | UNIQUE(doctor_id, starts_at) | Yes | "Slot just taken" 422 |
| Place order | UNIQUE(order_number); key replay | Yes | replay stored response |
| Newsletter signup | UNIQUE(email) | No | silent success (no enumeration) |

## 6. Data & privacy
- PII collected: (name, phone, email…) — mirror `PII` tags in SCHEMA.md Part B.
- Retention: (e.g. leads pruned after 12 months; cancelled bookings after 24.)
- Regulatory notes: (UAE PDPL considerations; payment data never stored — gateway tokens only.)
- `[DECIDE]` Data residency requirement? (affects hosting region)

## 7. Integrations
| Service | Purpose | Env keys | Failure behavior |
|---|---|---|---|
| e.g. Stripe | payments | STRIPE_* | queue retry ×3 → admin alert |
| e.g. WhatsApp/SMS | notifications | | degrade to email |

All integrations behind interfaces (RULES §3-D) with a fake for tests.

## 8. Non-functional requirements
- **Performance:** p95 read < 200ms, write < 500ms; key pages Lighthouse ≥ 90. Expected load: `[DECIDE]` (peak users/day → informs Octane/replica triggers, ARCHITECTURE §9).
- **Security:** RULES §5 applies fully; extra project-specific items here (e.g. 2FA for admin).
- **Availability & ops:** backups nightly; uptime target; error alerting channel.
- **Languages:** EN / AR `[DECIDE]` — if AR: which content is translated vs mirrored?
- **Devices:** mobile-first; minimum supported width 360px.
- **SEO:** SSR pages list: (home, listings, blog…). Sitemap + OG required.

## 9. Pages & flows inventory
| Page | Route | Auth | SSR | Key components |
|---|---|---|---|---|
| Home | / | guest | yes | Hero, FeaturedX |
| … | | | | |

Critical user flows (link or embed step tables — each flow gets a feature test end-to-end):
1. Flow A: …
2. Flow B: …

## 10. Milestones & definition of done
| Milestone | Contents | Target date |
|---|---|---|
| M1 Skeleton | migrations, models, seeders, auth, layouts | |
| M2 Core flow | primary user flow end-to-end + tests | |
| M3 Admin | management surfaces | |
| M4 Launch | polish, RTL, Lighthouse, deploy, DNS, backups verified | |

**Project DoD:** all Must features accepted · CI green (Pint, Larastan, ESLint, tsc, Pest) · security checklist (RULES §5) walked · SCHEMA.md Part B current · staging demo approved by client.

## 11. Open questions / decision log
| Date | Question | Decision | By |
|---|---|---|---|
| | | | |
