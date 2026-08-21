# PRD.md — Product Requirements

> **Stack (resolved by STARTUP.md gate):** Laravel + React (Inertia + React 19 + TypeScript).
> Persisted in `.env` (`APP_STACK=react`) and `config/features.php` (`stack`). The first-run
> gate is satisfied — do not ask the stack question again for this project.
>
> Read LAST, build FIRST from this. RULES/ARCHITECTURE/SCHEMA/DESIGN say HOW; this file says
> WHAT and WHY. If a requested feature is not here, add it here first — no ghost features.
> Anything marked `[DECIDE]` blocks related implementation until resolved.
> **Version-number caveat:** the installed stack is Laravel 13 / Inertia 3 / PHPUnit — see
> `CLAUDE.md` §2–§3 for the authoritative list and the accepted deviations.

---

## 1. Overview
- **Project name / codename:** crm-pdf-parser
- **One-liner:** Internal CRM tool that turns a candidate's PDF resume into an ATS-safe
  resume rendered in the chosen client company's house format.
- **Client / owner:** internal (devio)
- **Business goal (measurable):** cut manual resume re-typing per submission from ~25 minutes
  to under 5 minutes of review.
- **Target launch date:** `[DECIDE]`  **Environment domains:** staging: `[DECIDE]` · production: `[DECIDE]`

## 2. Users & roles
| Role | Description | Can | Cannot |
|---|---|---|---|
| Guest | not signed in | reach the login screen only | anything else |
| CRM user (v1) | recruiter / consultant, signs in with **username + password** | manage companies, upload resumes, view any ATS preview, re-parse | download or delete a resume they did not upload |
| Admin | `[DECIDE]` — no separate role in v1 | — | — |

Authorization is Policies + `$this->authorize()`. There is no RBAC package yet
(`ENABLE_SPATIE=false`), so the `Gate::before` allow-all bypass is active **outside tests** —
see CLAUDE.md §5. Introducing a real Admin/CRM-user split means turning Spatie on.

## 3. Scope

### In scope (v1) — ranked
| # | Feature | User story | Priority | Acceptance criteria |
|---|---|---|---|---|
| 0 | Resume template CRUD | As a recruiter I want to manage the house styles themselves, so several companies can share one format and I can change it in one place. | Must | Create/edit/archive works; a template is a base layout (`TemplateLayout`) plus its section order; slug unique incl. archived; a template still assigned to a company cannot be archived; only active templates are offered when assigning a company. |
| 1 | Company CRUD with logo | As a recruiter I want to register a client company with its logo, brand colour and resume house style, so uploaded resumes come out in that company's format. | Must | The company picks one **template** from the managed list (dropdown); create/edit/archive works; logo validated (png/jpg/webp, ≥64², ≤2 MB) and **re-encoded**; slug unique incl. archived; archive is a soft delete that keeps resumes. |
| 2 | Resume upload against a company | As a recruiter I pick a company and upload a candidate PDF. | Must | PDF-only (real mime checked), ≤10 MB, stored on the **private** disk with a random filename; parsing queued, page returns immediately; re-uploading the same file for the same company returns the existing record, never a duplicate or a 500. |
| 3 | Automatic parsing via the Python sidecar | The document is parsed into structured data without manual entry. | Must | `POST /v1/parse` returns contact/summary/experience/education/skills/certifications/languages; two-column layouts reconstructed; failures set status `failed` with a user-safe reason and are retryable. |
| 4 | ATS preview in the company's format | I see the resume laid out in the template that was in force when I uploaded it, ready to print/save as PDF. | Must | Section order = the resume's frozen template, else its layout default; unknown keys ignored; empty sections omitted; text ATS-normalised; **the preview shows the uploaded file's own data** (`AtsPreviewTest` proves it). |
| 5 | ATS readiness score | I see what would make this resume fail an ATS. | Should | 0–100 with band + actionable notes (missing email/phone/summary/experience, <5 skills, no quantified achievements). |
| 6 | ATS PDF export | As a recruiter I download the reformatted resume as a PDF to send on. | Must | The file contains the preview's content and nothing else (no app chrome), rendered server-side by dompdf in the resume's frozen template; **text stays text** so an ATS can read it; named after the candidate and company. |
| 7 | Re-parse, re-style + original download | I can retry a parse, switch this document to another template, and fetch the source PDF. | Should | Re-parse throttled 10/min and resets the status first; re-styling never touches `parsed_data`; download restricted to the uploader. |
| 8 | Dashboard | As a recruiter I open the app and see what came in, what is stuck, and what needs me. | Should | Counts (resumes, failed, companies, templates); 14-day intake chart; parse-pipeline shares; average ATS readiness over the 100 newest parsed documents; needs-attention list with failed parses first; latest uploads; busiest companies; template usage. Polls itself while anything is queued. Empty state on a fresh install rather than a wall of zeros. |

### Explicitly OUT of scope (v1)
- OCR for scanned resumes (returns a clear 422 instead).
- LLM-based extraction or rewriting of resume content.
- ~~Server-side PDF generation~~ — **now in scope** (feature 6): `barryvdh/laravel-dompdf`
  renders the ATS document server-side so the export contains only the resume, with real
  text. Browser print still works but is no longer the delivery mechanism.
- Candidate-facing access, job/vacancy records, submission tracking, e-mail sending.
- Bilingual EN/AR + RTL (internal tool; logical CSS properties used so it stays cheap).
- Bulk / ZIP upload.

## 4. Domain rules (business logic source of truth)
- **BR-1:** A company's `slug` is unique across all companies **including archived ones** — generated by `UniqueCompanySlug`, enforced by `UNIQUE(slug)`.
- **BR-2:** Renaming a company re-slugs it; the old slug is released.
- **BR-3:** The same file cannot be ingested twice for one company — `UNIQUE(company_id, file_hash)` (sha256). The Action returns the existing resume; a lost race is caught and also returns it. The same file **may** be filed against a different company.
- **BR-4:** Only an **active** company accepts uploads.
- **BR-5:** Parsing never runs in a request: `StoreResume` → `ParseResumeJob` (3 tries, 10s/60s backoff) → `ParseResume`.
- **BR-6:** Section order in force = the template's `section_order` if set, else `TemplateLayout::defaultSectionOrder()`. Unknown keys are dropped; sections with no content are omitted.
- **BR-6a:** A resume records the template it was uploaded against (`resumes.resume_template_id`, set by `StoreResume`). Re-pointing a company at another template changes only what is uploaded from then on.
- **BR-6b:** A template assigned to at least one company cannot be archived — reassign those companies first (`ResumeTemplatePolicy::delete`). Archiving is a soft delete, so resumes frozen against it keep rendering.
- **BR-6c:** A template may be built from a sample resume. The PDF is stored privately and read **on the queue** (`DeriveTemplateSectionsJob`); the sections that document printed, in its order, become the template's `section_order`. Only structure is taken — typography and header shape belong to the base layout. A sample with no recognisable sections is reported and the layout default is kept.
- **BR-6d:** An existing document can be re-styled (`PUT /resumes/{id}/template`) without re-parsing: `parsed_data` is untouched, so the preview and the PDF simply re-lay it out. Re-parsing is the separate action, and it resets the status first — `ParseResumeJob` skips resumes that are already `parsed`.
- **BR-7:** ATS score starts at 100 and deducts: no email −20, no phone −10, no summary −10, no experience −25, <5 skills −10, no quantified achievement −5. Bands: ≥85 strong, ≥65 fair, else weak. One definition (`Services\Ats\AtsScore`) serves both the preview and the dashboard average; the average is taken over the 100 newest parsed documents so it stays cheap.
- **BR-8:** Only the uploader may download or delete a resume (`ResumePolicy`).
- **BR-9:** Archiving a company soft-deletes it and sets `is_active = false`; its resumes stay for audit.
- **BR-10:** Logos are decoded, downscaled to ≤512px and **re-encoded as PNG** — the original container never reaches storage. SVG is refused.

## 5. Non-duplicate / idempotency map
| Operation | DB constraint | Idempotency-Key required? | On duplicate |
|---|---|---|---|
| Create company | `UNIQUE(slug)`, `UNIQUE(public_id)` | No | slug suffixed `-2`, `-3`, … |
| Upload resume | `UNIQUE(company_id, file_hash)` | No — the file hash *is* the key | return the existing resume (no new row, no new job) |
| Re-parse | — (idempotent by design) | No | `ParseResumeJob` returns early if already parsed |

## 6. Data & privacy
- **PII collected:** company contact email/phone; candidate name, email, phone, location,
  full employment + education history (in `resumes.parsed_data`) and the source PDF.
  Mirrored in SCHEMA.md Part B's PII register.
- Resumes live on a **private** disk and are served only through an authorized route.
  Parsed contact data is never written to logs (`ParseResume` logs identifiers only).
- The sidecar is stateless: it holds the PDF in memory for one request and logs page/character
  counts only.
- **Retention:** `[DECIDE]` — proposal: purge resumes + stored PDFs 12 months after upload
  (scheduled prune), companies retained while the client is active.
- **Regulatory:** UAE PDPL — candidate consent is captured by the recruiter outside this tool
  `[DECIDE]`. No payment data anywhere.

## 7. Integrations
| Service | Purpose | Env keys | Failure behavior |
|---|---|---|---|
| Python parsing sidecar (`sidecar/`) | PDF → structured resume | `SIDECAR_DRIVER`, `SIDECAR_URL`, `SIDECAR_TOKEN`, `SIDECAR_TIMEOUT` | 2 retries, then status `failed` + user-safe message + manual re-parse. Never a 500. |

Behind `App\Contracts\ResumeParser` with `FakeResumeParser` as the test/offline fake
(RULES §3-D). **`SIDECAR_DRIVER=fake` returns clearly-labelled SAMPLE data and ignores the
upload — production must be `sidecar`.**

## 8. Non-functional requirements
- **Performance:** upload response < 500ms (parsing is queued); preview read < 200ms p95.
  Expected load: `[DECIDE]` (est. tens of resumes/day → single queue worker is enough).
- **Security:** RULES §5 applies. Sidecar bound to localhost/private network with a bearer
  token; never internet-exposed.
- **Availability & ops:** one `queue:work` worker required, or resumes stay `pending`.
  Sidecar must be running or parses fail (retryable). Backups: `[DECIDE]`.
- **Languages:** EN only (see out-of-scope).
- **Devices:** desktop-first (recruiter workstation), usable from 360px.
- **SEO:** none — the whole app is behind auth; `/` redirects to login.

## 9. Pages & flows inventory
| Page | Route | Auth | Key components |
|---|---|---|---|
| Login (front door) | `/` → `/login` | guest | Fortify login by **username** |
| Dashboard | `/dashboard` | auth+verified | `stat-tile`, `upload-trend`, `resume-row`, pipeline shares, ATS average |
| Templates list | `/resume-templates` | auth+verified | search, template cards w/ usage counts, archive dialog |
| New / edit template | `/resume-templates/create`, `/resume-templates/{slug}/edit` | auth+verified | `resume-template-form`, `section-order-picker` |
| Companies list | `/companies` | auth+verified | search, company cards, pagination |
| Add / edit company | `/companies/create`, `/companies/{slug}/edit` | auth+verified | `company-form`, logo input, template dropdown + `template-summary` |
| Company detail | `/companies/{slug}` | auth+verified | `resume-upload-card`, resume list w/ live status polling |
| ATS preview | `/resumes/{public_id}` | auth+verified | `ats-resume-preview`, `resume-template-switcher`, readiness score, parser notes, PDF export |

Critical flows (each covered by a feature test):
1. Create a template → assign it to a company with a logo → the company lists that template.
2. Pick company → upload PDF → queued → status flips to Parsed → preview shows **that file's**
   data in the company's section order (`AtsPreviewTest`).
3. Upload the same file again → no duplicate.
4. Sidecar down → status `failed` with a readable reason → re-parse succeeds.
5. Re-point a company at another template → resumes already uploaded keep their own style
   (`ResumeTemplateManagementTest`).
6. Switch one document to another template → it re-lays out immediately, `parsed_data`
   unchanged; re-parse re-reads the source PDF (`ResumeRestyleTest`).
7. Download the ATS PDF → a text-based PDF of the preview only (`AtsPdfExportTest`).
8. Build a template from a sample resume → its printed section order is adopted
   (`TemplateFromSampleTest`).

## 10. Milestones & definition of done
| Milestone | Contents | Status |
|---|---|---|
| M1 Skeleton | migrations, models, enums, policies, seeders, routes | **done** |
| M2 Core flow | company CRUD + upload + sidecar parse + ATS preview end-to-end | **done** |
| M3 Hardening | retention prune, RBAC (`ENABLE_SPATIE`), MySQL for tests, sidecar deployment | open |
| M4 Launch | Lighthouse pass, deploy, worker + sidecar supervision, backups | open |

**Project DoD:** all Must features accepted · CI green (Pint, PHPStan, ESLint, tsc, PHPUnit,
pytest) · RULES §5 walked · SCHEMA.md Part B current · staging demo approved.

## 11. Open questions / decision log
| Date | Question | Decision | By |
|---|---|---|---|
| 2026-08-20 | Frontend stack | Laravel + React (Inertia), starter kit already wired | user |
| 2026-08-20 | Sign-in identifier | **username + password** (not email) | user |
| 2026-08-20 | Landing page | `/` redirects to login (no marketing page) | user |
| 2026-08-20 | Parsing approach | deterministic rule-based Python sidecar; no LLM in v1 | assistant (documented) |
| 2026-08-20 | Test database | SQLite `:memory:` accepted temporarily; MySQL when provisioned | assistant (documented, RULES §7 deviation) |
| — | Retention period for resumes/PII | `[DECIDE]` | |
| — | Roles beyond a single CRM user | `[DECIDE]` | |
| — | Hosting / data residency | `[DECIDE]` | |
