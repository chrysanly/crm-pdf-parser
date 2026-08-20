"""FastAPI resume-parsing sidecar.

The only consumer is App\\Services\\Parsing\\SidecarResumeParser. It exposes one
real endpoint (POST /v1/parse) plus a health check, is stateless, stores nothing,
and logs no PII.
"""

from __future__ import annotations

import logging
import os

from fastapi import Depends, FastAPI, File, HTTPException, UploadFile, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from .extraction import extract
from .parsing import parse
from .schemas import PARSER_VERSION, ParsedResume

MAX_BYTES = int(os.getenv("SIDECAR_MAX_BYTES", str(10 * 1024 * 1024)))
TOKEN = os.getenv("SIDECAR_TOKEN", "")

logger = logging.getLogger("sidecar")

app = FastAPI(
    title="Resume Parsing Sidecar",
    version=PARSER_VERSION,
    docs_url="/docs" if os.getenv("SIDECAR_DOCS", "0") == "1" else None,
)

bearer = HTTPBearer(auto_error=False)


def require_token(
    credentials: HTTPAuthorizationCredentials | None = Depends(bearer),
) -> None:
    """Shared-secret auth. The sidecar must never be reachable from the internet."""
    if not TOKEN:
        return  # local development without a token configured

    if credentials is None or credentials.credentials != TOKEN:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing bearer token.",
        )


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "parser_version": PARSER_VERSION}


@app.post("/v1/parse", response_model=ParsedResume)
async def parse_resume(
    file: UploadFile = File(...),
    _: None = Depends(require_token),
) -> ParsedResume:
    content = await file.read()

    if not content:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, "the uploaded file was empty")

    if len(content) > MAX_BYTES:
        raise HTTPException(status.HTTP_413_REQUEST_ENTITY_TOO_LARGE, "the file is too large")

    if not content.startswith(b"%PDF"):
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, "the file is not a PDF")

    try:
        document = extract(content)
    except Exception as exception:  # pdfplumber raises a wide range of parse errors
        logger.warning("extraction_failed: %s", type(exception).__name__)
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_ENTITY,
            "the PDF structure could not be read",
        ) from exception

    if not document.text.strip():
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_ENTITY,
            "no readable text was found (a scanned image needs OCR)",
        )

    # Filename and content are deliberately absent from the log line: PII.
    logger.info("parsed pages=%s chars=%s", document.page_count, len(document.text))

    return parse(document.text, page_count=document.page_count, warnings=document.warnings)
