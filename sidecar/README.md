# Resume Parsing Sidecar

FastAPI service that turns a PDF resume into the structured JSON Laravel expects.
It is the **only** Python in this project, and the only thing that reads PDFs.

Laravel talks to it through `App\Contracts\ResumeParser`
(`SidecarResumeParser` in production, `FakeResumeParser` in tests) — nothing else in
the app knows this service exists.

## Contract

```
POST /v1/parse        multipart: file=<resume.pdf>     Authorization: Bearer <token>
      200 -> ParsedResume JSON (see app/schemas.py)
      422 -> {"detail": "<user-safe reason>"}    not a PDF / no readable text
      401 -> invalid or missing token
GET  /health          -> {"status": "ok", "parser_version": "..."}
```

`app/schemas.py` **is** the contract. Its field names are the JSON keys read by
`App\DTOs\Parsing\ParsedResume`; changing one is a breaking change on both sides and
must be done in the same commit as the PHP DTO.

## Run it

```bash
cd sidecar
python -m venv .venv
.venv/Scripts/pip install -r requirements.txt        # Linux/macOS: .venv/bin/pip

.venv/Scripts/python -m uvicorn app.main:app --reload --port 8001
```

Then point Laravel at it in `.env`:

```env
SIDECAR_DRIVER=sidecar          # 'fake' returns a fixed sample document
SIDECAR_URL=http://127.0.0.1:8001
SIDECAR_TOKEN=<same value as the sidecar's SIDECAR_TOKEN>
SIDECAR_TIMEOUT=30
```

Environment variables read by the service:

| Variable | Default | Purpose |
|---|---|---|
| `SIDECAR_TOKEN` | *(empty)* | Shared bearer secret. Empty = auth disabled (local only). |
| `SIDECAR_MAX_BYTES` | 10485760 | Hard upload ceiling. |
| `SIDECAR_DOCS` | `0` | `1` exposes `/docs`. |

## Tests

```bash
.venv/Scripts/python -m pytest
```

## How the parsing works

1. **`extraction.py`** — pdfplumber text extraction. Detects a two-column layout by
   looking for a vertical gutter in the word positions and reads the left column
   before the right one, which is the usual cause of scrambled resume text. Pages
   with no extractable text are reported as warnings (a scan needs OCR).
2. **`parsing.py`** — deterministic, rule-based structuring: segment into sections by
   heading aliases, then extract contact details, roles with date ranges, education,
   and comma/bullet lists. No model call and no network, so the same PDF always
   produces the same JSON and Laravel-side tests stay stable.

### Deliberate limits

- **No OCR.** A scanned image returns 422 rather than guessing.
- **No LLM.** If fuzzier extraction is needed later, add it as a *second* parser
  behind the same schema and pick between them by config — do not make the
  rule-based path non-deterministic.
- **Stateless.** Nothing is written to disk; the PDF lives only in memory for the
  duration of the request. Logs carry page and character counts, never PII.

## Security

The sidecar must never be exposed to the internet — bind it to localhost or keep it
on a private network, and always set `SIDECAR_TOKEN` outside local development. It
performs no authorization of its own beyond the shared secret.
