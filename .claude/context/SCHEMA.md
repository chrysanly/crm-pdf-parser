# SCHEMA.md — Database Conventions & Living Schema

> Read after ARCHITECTURE.md. Part A is fixed conventions for ALL projects.
> Part B is the living schema of THIS project — the AI must update it whenever a migration is added.
> **Rule: no migration is written unless it complies with Part A and is reflected in Part B in the same PR.**

---

## PART A — Conventions (all projects)

### A1. Naming
- Tables: `snake_case`, plural (`order_items`). Pivots: singular, alphabetical (`doctor_service`).
- Columns: `snake_case`, singular. FKs: `{singular_relation}_id`. Booleans: `is_/has_` prefix. Timestamps of events: `{verb}_at` (`published_at`, `confirmed_at`).
- Indexes named by Laravel defaults; custom composites: `idx_{table}_{cols}`.

### A2. Keys & identifiers
- PK: `$table->id()` (BIGINT unsigned) internally.
- **Public-facing identifiers are ULIDs or slugs, never auto-increment IDs** (`$table->ulid('public_id')->unique()` or `slug` unique). Prevents enumeration/IDOR scraping.
- Every FK declared with constraint: `$table->foreignId('user_id')->constrained()->cascadeOnDelete()` (or `restrictOnDelete()` for protected parents — decide explicitly, never default silently).

### A3. Types (strict)
| Data | Type | Never |
|---|---|---|
| Money | `DECIMAL(12,2)` (or integer minor units for gateways) | FLOAT/DOUBLE |
| Status/type | string ENUM cast to PHP backed enum; column `string(30)` + CHECK or native enum per DB | free text |
| Phone | `string(20)`, E.164 normalized in `prepareForValidation` | integer |
| Flexible blobs | `JSON` with documented shape here | serialized PHP |
| Coordinates | `DECIMAL(10,7)/(11,7)` or POINT + spatial index if radius queries | float |
| Long text | `TEXT/LONGTEXT`, never indexed raw — use FULLTEXT or Scout | VARCHAR(5000) |

### A4. Integrity = duplicates die at the database
- Every business uniqueness rule becomes a **UNIQUE constraint**, not just validation:
  - `UNIQUE(email)` · `UNIQUE(doctor_id, starts_at)` · `UNIQUE(order_number)` · `UNIQUE(user_id, listing_id)` on favourites.
- Validation (`Rule::unique()`) is UX; the constraint is truth; the Action catches `UniqueConstraintViolationException` → domain error. All three or it's not done.
- `NOT NULL` by default; nullable is an explicit modeling decision with a comment.
- CHECK constraints for invariants where supported (`price >= 0`, `ends_at > starts_at`).

### A5. Indexing playbook (performance)
1. Index every column in `WHERE`, `ORDER BY`, `JOIN`, and FK columns (Laravel adds FK indexes via `constrained()`).
2. Composite indexes ordered: equality columns first, then range/sort — e.g. filter `status = ? AND type = ? AND price BETWEEN` → `INDEX(status, type, price)`.
3. Covering slim queries: pair with `select()` so hot list pages hit index-only reads where possible.
4. No index on low-cardinality booleans alone; combine into composites.
5. Any query on a table expected >10k rows ships with an `EXPLAIN` check in the PR description.
6. Soft-deleted tables: include `deleted_at` in hot composites or use partial indexes (Postgres).

### A6. Migrations discipline
- Append-only after merge. Fix-forward with new migrations.
- Every migration reversible (`down()` real, not empty) until first production deploy; after that, forward-only + backups.
- Zero-downtime rules for live tables: add nullable column → backfill in chunked job → add constraint; never `change()` a huge table in one shot.
- Seeders: `DatabaseSeeder` = realistic demo data via factories; `ProductionSeeder` = reference data only (roles, settings). Factories define states (`published()`, `outOfStock()`).

### A7. Data protection
- PII columns minimal and listed in Part B with a `PII` tag. Encrypted casts for sensitive-at-rest (`'token' => 'encrypted'`).
- Passwords: `hashed` cast only. Never log PII; scrub in log context.
- Retention: soft-deleted user data pruned by scheduled `model:prune` per policy in PRD.md.
- Backups: nightly automated (spatie/laravel-backup or managed DB snapshots), restore tested quarterly.

### A8. Concurrency
- Stock/balance mutations: `lockForUpdate()` inside the transaction, or atomic `decrement()` guarded by `WHERE stock >= ?` and affected-rows check.
- Long-running human edits: optimistic locking via `updated_at`/version column check.

---

## PART B — Living schema (THIS project)

> Keep in sync with `database/migrations/`. A migration whose table is not documented
> here is not done (CLAUDE.md §6.6).

### users
| Column | Type | Constraints/Index | Notes |
|---|---|---|---|
| id | bigint UN | PK | |
| name | string(255) | NOT NULL | PII |
| username | string(50) | UNIQUE, NOT NULL | **sign-in identifier** (`config/fortify.php` `username`) |
| email | string(255) | UNIQUE | PII, used for password reset + verification only |
| email_verified_at | timestamp | nullable | |
| password | string | `hashed` cast | |
| two_factor_secret / two_factor_recovery_codes | text | nullable | encrypted by Fortify |
| two_factor_confirmed_at | timestamp | nullable | |
| remember_token | string(100) | nullable | |
| timestamps | | | |

**Relations:** hasMany resumes (as uploader), hasMany passkeys.
**Uniqueness rules:** `username`, `email`.
**Hot queries:** login by username (UNIQUE covers it).

### companies
| Column | Type | Constraints/Index | Notes |
|---|---|---|---|
| id | bigint UN | PK | internal only |
| public_id | ulid | UNIQUE | exposed identifier (used in resume storage paths) |
| name | string(150) | NOT NULL | |
| slug | string(170) | UNIQUE | public route key (`/companies/{slug}`) |
| industry | string(100) | nullable | |
| contact_email | string(255) | nullable | PII |
| contact_phone | string(20) | nullable | PII, E.164 normalised in the FormRequest |
| website | string(255) | nullable | |
| logo_path | string(255) | nullable | path on the **public** disk; random filename, re-encoded PNG |
| brand_color | string(7) | NOT NULL, default `#1F2937` | hex, validated by regex |
| resume_template_id | bigint UN | FK → resume_templates, restrict, NOT NULL | the house style in force; the form submits the template **slug** |
| formatting_notes | text | nullable | house-style hints shown beside the ATS preview |
| is_active | boolean | NOT NULL, default true | inactive = no new uploads |
| timestamps / deleted_at | | idx(deleted_at), idx(is_active, name) | soft deletes = archive |

**Relations:** hasMany resumes (cascade on delete), belongsTo resumeTemplate.
**Uniqueness rules:** `slug` (generated by `UniqueCompanySlug`, checked including trashed rows), `public_id`.
**Hot queries:** active companies ordered by name → `INDEX(is_active, name)`; lookup by slug → UNIQUE.
**Note:** the section order lives on the template, not here — one house style, one definition.

### resume_templates
| Column | Type | Constraints/Index | Notes |
|---|---|---|---|
| id | bigint UN | PK | internal only |
| public_id | ulid | UNIQUE | exposed identifier |
| name | string(100) | NOT NULL | e.g. "Al Mutakamela house style" |
| slug | string(120) | UNIQUE | public route key (`/resume-templates/{slug}`) |
| description | string(255) | nullable | shown on the template card |
| layout | string(30) | NOT NULL, default `classic` | enum `TemplateLayout` — which React renderer draws it |
| section_order | json | nullable | `list<string>` overriding the layout default |
| sample_path | string(255) | nullable | sample resume on the **private** disk, random filename — a real CV, never served |
| sample_filename | string(255) | nullable | client-supplied name, display only |
| sample_status | string(30) | nullable | enum `ResumeStatus` — the queued read of the sample |
| sample_failure_reason | text | nullable | user-safe reason the sample could not be read |
| is_active | boolean | NOT NULL, default true | inactive = not offered when assigning a company |
| timestamps / deleted_at | | idx(deleted_at), idx(is_active, name) | soft deletes = archive |

**Relations:** hasMany companies, hasMany resumes.
**Uniqueness rules:** `slug` (generated by `UniqueResumeTemplateSlug`, checked including trashed rows), `public_id`.
**Hot queries:** active templates ordered by name → `INDEX(is_active, name)`; lookup by slug → UNIQUE.
**Sample rule:** uploading a sample queues `DeriveTemplateSectionsJob`; the order the
sample printed its sections in replaces `section_order`. Nothing else is copied from it.
**Deletion rule:** a template still assigned to a company cannot be archived (`ResumeTemplatePolicy::delete` + `DeleteResumeTemplate`); resumes that froze it keep rendering because a soft delete leaves the row in place.
**JSON shape — `section_order`:**
```json
["details", "summary", "skills", "experience", "certifications", "education", "languages"]
```

### resumes
| Column | Type | Constraints/Index | Notes |
|---|---|---|---|
| id | bigint UN | PK | |
| public_id | ulid | UNIQUE | route key (`/resumes/{public_id}`) |
| company_id | bigint UN | FK → companies, cascade | |
| resume_template_id | bigint UN | FK → resume_templates, null on delete | **frozen at upload** — restyling the company never restyles this document |
| uploaded_by | bigint UN | FK → users, restrict | download/delete authorization |
| original_filename | string(255) | NOT NULL | client-supplied, never used as a storage path |
| stored_path | string(255) | NOT NULL | **private** disk (`config('crm.resume_disk')`), random filename |
| file_hash | char(64) | part of UNIQUE | sha256 of the upload — idempotency key |
| file_size | uint | NOT NULL | bytes |
| page_count | usmallint | nullable | filled by the parser |
| status | string(30) | NOT NULL, default `pending` | enum `ResumeStatus` |
| candidate_name | string(150) | nullable | **PII**, denormalised from `parsed_data` for list pages |
| candidate_email | string(255) | nullable | **PII**, denormalised |
| parsed_data | json | nullable | `ParsedResume` shape (below) |
| failure_reason | text | nullable | user-safe message from the domain exception |
| parsed_at | timestamp | nullable | |
| timestamps | | | |

**Relations:** belongsTo company, belongsTo resumeTemplate (the frozen style), belongsTo uploader (users).
**Uniqueness rules:** `UNIQUE(company_id, file_hash)` — the same document cannot be ingested twice for one company; `StoreResume` catches the violation and returns the existing row.
**Hot queries:** a company's resumes newest-first, filtered by status → `INDEX(company_id, status, created_at)`.
**JSON shape — `parsed_data`** (mirrored by `App\DTOs\Parsing\ParsedResume` and `sidecar/app/schemas.py`):
```json
{
  "contact": { "full_name": "", "email": "", "phone": "", "location": "", "linkedin": "", "website": null },
  "headline": "",
  "section_order": ["summary", "experience", "education", "skills"],
  "summary": "",
  "experience": [
    { "title": "", "company": "", "location": "", "start_date": "2021-03", "end_date": null, "is_current": true, "highlights": [""] }
  ],
  "education": [
    { "degree": "", "institution": "", "location": null, "start_date": "2013-09", "end_date": "2017-06" }
  ],
  "skills": [""],
  "certifications": [""],
  "languages": [""],
  "warnings": [""],
  "page_count": 2,
  "parser_version": "sidecar-1.0"
}
```

### PII register
| Table | Columns | Retention |
|---|---|---|
| users | name, email | life of the account |
| companies | contact_email, contact_phone | life of the company record |
| resumes | candidate_name, candidate_email, `parsed_data` (employment history), stored PDF | `[DECIDE]` — see PRD §6 |
| resume_templates | `sample_path` (a real candidate CV, stored to derive the section order) | until the sample is replaced or removed |

### ER summary
```
users 1--* resumes *--1 companies *--1 resume_templates
resumes *--1 resume_templates          (frozen at upload)
users 1--* passkeys
```
