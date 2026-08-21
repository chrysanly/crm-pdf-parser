"""PDF -> plain text, with the reading-order problems resumes actually have.

Two-column resumes are the common failure: naive extraction interleaves the
sidebar with the main column. We detect a wide horizontal gap in the word
positions and, when present, read the left column fully before the right one.

The second failure is glyphs a PDF never maps to Unicode. Word-exported resumes
routinely draw their bullets, dashes and contact icons from a font with no
usable cmap, so the extracted text carries "(cid:127)", U+FFFD or a stray
dingbat letter where a bullet or an en dash belongs. Every downstream rule in
parsing.py keys off exactly those characters, so the repair happens here, once,
before any text leaves this module.
"""

from __future__ import annotations

import io
import re
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

            marker_glyphs = _symbol_glyphs(page)

            if split_x is None:
                pages.append(_sanitise(page.extract_text() or "", marker_glyphs))
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


#: Fonts that draw pictures, not letters. A character from one of these carries
#: no textual meaning: it is a bullet, a tick or a contact icon.
SYMBOL_FONTS = ("zapfdingbats", "dingbat", "wingding", "webding", "symbol", "fontawesome", "glyphicons")

#: A glyph the PDF declares but never maps to Unicode.
CID_RE = re.compile(r"\(cid:\d+\)")

#: What pdfminer emits when a mapping exists but decodes to nothing usable.
UNMAPPED = "\ufffd"

BULLET = "\u2022"
EN_DASH = "\u2013"
APOSTROPHE = "\u2019"


def _symbol_glyphs(page: pdfplumber.page.Page) -> frozenset[str]:
    """Single characters this page draws in a symbol font.

    Returned as the literal text pdfminer produced for them (ZapfDingbats "n",
    for instance), so `_sanitise` can recognise those same characters in the
    extracted text without having to re-derive positions.
    """
    glyphs = {
        char["text"]
        for char in page.chars
        if any(font in str(char.get("fontname", "")).lower() for font in SYMBOL_FONTS)
        and len(char.get("text", "")) == 1
        and not char["text"].isspace()
    }

    return frozenset(glyphs)


def _sanitise(text: str, marker_glyphs: frozenset[str] = frozenset()) -> str:
    """Turn unmapped glyphs back into the punctuation they were drawn as.

    Three repairs, each narrow on purpose:

    * `(cid:N)` — no Unicode at all. At the start of a line it is the list
      bullet (that is where resumes put unmappable glyphs); anywhere else it is
      dropped rather than guessed at.
    * a symbol-font glyph opening a line — a decorative marker (the icon beside
      an email address, a dingbat bullet) and becomes a real bullet.
    * U+FFFD — standing alone between spaces it separated two fields and becomes
      an en dash, which the date-range and "role - employer" rules need; hanging
      off the end of a word it was an apostrophe. Anything else is dropped
      rather than guessed at.
    """
    lines: list[str] = []

    for raw in text.splitlines():
        line = raw

        if marker_glyphs:
            line = re.sub(
                rf"^\s*(?:{'|'.join(re.escape(glyph) for glyph in sorted(marker_glyphs))})(?=\s)",
                BULLET,
                line,
            )

        line = CID_RE.sub(lambda match: BULLET if match.start() == 0 else "", line, count=1)
        line = CID_RE.sub("", line)

        if UNMAPPED in line:
            line = re.sub(rf"\s{re.escape(UNMAPPED)}\s", f" {EN_DASH} ", line)
            line = re.sub(rf"(?<=\w){re.escape(UNMAPPED)}", APOSTROPHE, line)
            line = line.replace(UNMAPPED, "")

        lines.append(re.sub(r"[ \t]{2,}", " ", line).rstrip())

    return "\n".join(lines)
