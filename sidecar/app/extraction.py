"""PDF -> plain text, with the reading-order problems resumes actually have.

Two-column resumes are the common failure: naive extraction interleaves the
sidebar with the main column. We detect a wide horizontal gap in the word
positions and, when present, read the left column fully before the right one.
"""

from __future__ import annotations

import io
from dataclasses import dataclass

import pdfplumber


@dataclass(frozen=True)
class ExtractedDocument:
    text: str
    page_count: int
    warnings: tuple[str, ...]

    @property
    def lines(self) -> list[str]:
        return [line.strip() for line in self.text.splitlines() if line.strip()]


def extract(content: bytes) -> ExtractedDocument:
    """Extract text from a PDF byte string. Never raises on malformed pages."""
    warnings: list[str] = []
    pages: list[str] = []

    with pdfplumber.open(io.BytesIO(content)) as pdf:
        page_count = len(pdf.pages)

        for page in pdf.pages:
            words = page.extract_words(use_text_flow=False, keep_blank_chars=False)

            if not words:
                warnings.append("A page contained no extractable text (likely a scanned image).")
                continue

            split_x = _column_split(words, float(page.width))

            if split_x is None:
                pages.append(page.extract_text() or "")
                continue

            warnings.append(
                "Source PDF used a two-column layout; reading order was reconstructed."
            )
            left = page.crop((0, 0, split_x, page.height)).extract_text() or ""
            right = page.crop((split_x, 0, page.width, page.height)).extract_text() or ""
            pages.append(f"{left}\n{right}")

    return ExtractedDocument(
        text="\n".join(pages).strip(),
        page_count=page_count,
        warnings=tuple(dict.fromkeys(warnings)),
    )


def _column_split(words: list[dict], page_width: float) -> float | None:
    """Return the x coordinate of a two-column gutter, or None for single column.

    A gutter is a vertical band in the middle third of the page that no word
    crosses and that is at least 8% of the page width.
    """
    if len(words) < 40:
        return None

    lower = page_width * 0.33
    upper = page_width * 0.66
    min_gap = page_width * 0.08

    spans = sorted((float(w["x0"]), float(w["x1"])) for w in words)

    cursor = spans[0][1]
    for start, end in spans[1:]:
        if start - cursor >= min_gap:
            gutter = (cursor + start) / 2
            if lower <= gutter <= upper:
                return gutter
        cursor = max(cursor, end)

    return None
