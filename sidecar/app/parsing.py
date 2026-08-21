"""Plain resume text -> ParsedResume.

Deterministic and rule-based on purpose: no model call, no network, same input
always gives the same output, so Laravel-side tests stay stable. The pipeline is
section segmentation first, then per-section extraction.
"""

from __future__ import annotations

import re

from .schemas import (
    PARSER_VERSION,
    Contact,
    EducationEntry,
    ExperienceEntry,
    ParsedResume,
)

# --- section detection -------------------------------------------------------

SECTION_ALIASES: dict[str, tuple[str, ...]] = {
    "summary": ("summary", "professional summary", "profile", "objective", "about me", "career summary"),
    "experience": (
        "experience", "work experience", "professional experience", "employment",
        "employment history", "career history", "work history",
    ),
    "education": ("education", "academic background", "qualifications", "academics"),
    "skills": ("skills", "core skills", "technical skills", "key skills", "competencies", "expertise"),
    "certifications": ("certifications", "certificates", "licenses", "training", "courses"),
    "languages": ("languages", "language skills"),
}

_HEADING_LOOKUP = {
    alias: key for key, aliases in SECTION_ALIASES.items() for alias in aliases
}

EMAIL_RE = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+")
PHONE_RE = re.compile(r"(?:\+|00)\s?\d[\d\s().-]{7,18}\d")
LINKEDIN_RE = re.compile(r"(?:https?://)?(?:[\w.]*\.)?linkedin\.com/in/[\w-]+", re.I)
URL_RE = re.compile(r"https?://[^\s,;]+", re.I)
BULLET_RE = re.compile(r"^\s*(?:[-*•▪●·‣⁃]|\d+[.)])\s+")

MONTHS = {
    "jan": 1, "january": 1, "feb": 2, "february": 2, "mar": 3, "march": 3,
    "apr": 4, "april": 4, "may": 5, "jun": 6, "june": 6, "jul": 7, "july": 7,
    "aug": 8, "august": 8, "sep": 9, "sept": 9, "september": 9, "oct": 10,
    "october": 10, "nov": 11, "november": 11, "dec": 12, "december": 12,
}

_MONTH_NAMES = "|".join(sorted(MONTHS, key=len, reverse=True))
_DATE = rf"(?:(?:{_MONTH_NAMES})\.?\s*)?\d{{4}}"
_PRESENT = r"present|current|now|to date|ongoing"
DATE_RANGE_RE = re.compile(
    rf"(?P<start>{_DATE})\s*(?:-|–|—|to|until|through)\s*(?P<end>{_DATE}|{_PRESENT})",
    re.I,
)
SEPARATOR_RE = re.compile(r"\s+(?:\||,|-|–|—|at|@)\s+", re.I)


def parse(text: str, page_count: int | None = None, warnings: tuple[str, ...] = ()) -> ParsedResume:
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    collected = list(warnings)

    sections = _segment(lines)
    header_lines = sections.get("_header", [])

    contact = _contact(header_lines or lines[:12], lines)
    summary = _summary(sections.get("summary", []), header_lines)
    experience = _experience(sections.get("experience", []), collected)
    education = _education(sections.get("education", []))
    skills = _flat_items(sections.get("skills", []))
    certifications = _flat_items(sections.get("certifications", []))
    languages = _flat_items(sections.get("languages", []))

    if not sections.get("experience"):
        collected.append("No experience heading was found; work history may be incomplete.")
    if contact.email is None:
        collected.append("No email address was found in the document.")

    return ParsedResume(
        contact=contact,
        summary=summary,
        experience=experience,
        education=education,
        skills=skills,
        certifications=certifications,
        languages=languages,
        warnings=list(dict.fromkeys(collected)),
        page_count=page_count,
        parser_version=PARSER_VERSION,
    )


def _segment(lines: list[str]) -> dict[str, list[str]]:
    """Split the document into sections keyed by canonical name.

    Everything before the first recognised heading is `_header` (name + contact).
    """
    sections: dict[str, list[str]] = {"_header": []}
    current = "_header"

    for line in lines:
        key = _heading_key(line)

        if key is not None:
            current = key
            sections.setdefault(current, [])
            continue

        sections.setdefault(current, []).append(line)

    return sections


def _heading_key(line: str) -> str | None:
    candidate = re.sub(r"[^a-z\s]", "", line.lower()).strip()

    if not candidate or len(candidate) > 40:
        return None

    # A heading is short, has no sentence punctuation, and matches an alias.
    if line.rstrip().endswith((".", ",", ";")):
        return None

    return _HEADING_LOOKUP.get(candidate)


def _contact(header: list[str], all_lines: list[str]) -> Contact:
    blob = "\n".join(header)
    full_blob = "\n".join(all_lines)

    email = _first(EMAIL_RE, blob) or _first(EMAIL_RE, full_blob)
    phone_raw = _first(PHONE_RE, blob) or _first(PHONE_RE, full_blob)
    linkedin = _first(LINKEDIN_RE, blob) or _first(LINKEDIN_RE, full_blob)

    website = None
    for url in URL_RE.findall(blob):
        if "linkedin.com" not in url.lower():
            website = url
            break

    return Contact(
        full_name=_name(header),
        email=email.lower() if email else None,
        phone=_normalise_phone(phone_raw) if phone_raw else None,
        location=_location(header),
        linkedin=linkedin,
        website=website,
    )


def _name(header: list[str]) -> str | None:
    """The name is the first line that reads like a person's name."""
    for line in header[:5]:
        if EMAIL_RE.search(line) or PHONE_RE.search(line) or URL_RE.search(line):
            continue

        words = [w for w in re.split(r"\s+", line.strip()) if w]

        if not 2 <= len(words) <= 4:
            continue
        if any(char.isdigit() for char in line):
            continue
        if len(line) > 60:
            continue
        if all(re.match(r"^[A-Z][\w'’-]*$", w) or w.isupper() for w in words):
            return line.strip().title() if line.isupper() else line.strip()

    return None


LOCATION_HINTS = (
    "dubai", "abu dhabi", "sharjah", "ajman", "uae", "united arab emirates",
    "riyadh", "doha", "kuwait", "cairo", "amman", "beirut", "london", "remote",
)


def _location(header: list[str]) -> str | None:
    for line in header:
        lowered = line.lower()
        if any(hint in lowered for hint in LOCATION_HINTS) and len(line) <= 80:
            cleaned = EMAIL_RE.sub("", line)
            cleaned = PHONE_RE.sub("", cleaned)
            cleaned = cleaned.strip(" |,-•")
            if cleaned:
                return cleaned
    return None


def _summary(section: list[str], header: list[str]) -> str | None:
    if section:
        return " ".join(BULLET_RE.sub("", line) for line in section).strip() or None

    # No summary heading: a long prose line in the header often is one.
    for line in header:
        if len(line) > 120 and not EMAIL_RE.search(line):
            return line.strip()

    return None


TRAILING_JUNK = " |,-–—•\t"


def _classify(line: str, previous: str | None) -> str:
    """Label one line of the experience section.

    Resumes wrap: a role header, its date range, and its achievement bullets are
    each free to land on their own line, and a long bullet continues on the next
    one. Getting these four cases apart is the whole job — misreading a wrapped
    line as a header invents a phantom job.
    """
    if BULLET_RE.match(line):
        return "bullet"

    match = DATE_RANGE_RE.search(line)
    remainder = DATE_RANGE_RE.sub("", line).strip(TRAILING_JUNK) if match else line

    if match and not remainder:
        return "dates"          # e.g. "Nov 2025 - Present" under its own header
    if match:
        return "header_dated"   # e.g. "Engineer | Acme    Mar 2021 - Present"

    # No dates: header or wrapped text?
    if len(line) > 120 or line[:1].islower():
        return "continuation"

    # " - " / " | " / " @ " between role and employer is the strongest header signal.
    if SEPARATOR_RE.search(line):
        return "header"

    # Otherwise a line following bullets/wrapped text is the tail of that text,
    # while a short Title-Case line on its own is a header.
    if previous in {"bullet", "continuation"}:
        return "continuation"

    return "header" if len(line.split()) <= 8 else "continuation"


def _blank_entry() -> dict:
    return {
        "title": "",
        "company": None,
        "location": None,
        "start_date": None,
        "end_date": None,
        "is_current": False,
        "highlights": [],
    }


def _apply_dates(entry: dict, match: re.Match[str], warnings: list[str]) -> None:
    end_raw = match.group("end")
    is_current = bool(re.fullmatch(_PRESENT, end_raw.strip(), re.I))

    if is_current:
        warnings.append('An open end date ("Present") was normalised to a current role.')

    entry["start_date"] = _iso_month(match.group("start"))
    entry["end_date"] = None if is_current else _iso_month(end_raw)
    entry["is_current"] = is_current


def _experience(section: list[str], warnings: list[str]) -> list[ExperienceEntry]:
    entries: list[ExperienceEntry] = []
    current: dict | None = None
    previous: str | None = None

    for line in section:
        kind = _classify(line, previous)
        previous = kind

        if kind in {"header", "header_dated"}:
            if current is not None:
                entries.append(_finish_experience(current))

            match = DATE_RANGE_RE.search(line) if kind == "header_dated" else None
            remainder = DATE_RANGE_RE.sub("", line).strip(TRAILING_JUNK) if match else line

            title, company, location = _split_header(remainder)
            current = _blank_entry()
            current.update({"title": title, "company": company, "location": location})

            if match is not None:
                _apply_dates(current, match, warnings)

            continue

        if current is None:
            current = _blank_entry()

        if kind == "dates":
            date_match = DATE_RANGE_RE.search(line)
            if date_match is not None:
                _apply_dates(current, date_match, warnings)
            continue

        text = BULLET_RE.sub("", line).strip()

        if not text:
            continue

        if kind == "bullet":
            current["highlights"].append(text)
            continue

        # Continuation: glue it back onto whatever it wrapped from.
        if current["highlights"]:
            current["highlights"][-1] = f"{current['highlights'][-1]} {text}".strip()
        elif not current["title"]:
            current["title"] = text
        elif current["company"] is None:
            current["company"] = text
        else:
            current["highlights"].append(text)

    if current is not None:
        entries.append(_finish_experience(current))

    return [entry for entry in entries if entry.title or entry.company]


def _finish_experience(payload: dict) -> ExperienceEntry:
    return ExperienceEntry(
        title=payload["title"] or "",
        company=payload["company"],
        location=payload["location"],
        start_date=payload["start_date"],
        end_date=payload["end_date"],
        is_current=payload["is_current"],
        highlights=payload["highlights"],
    )


def _split_role(line: str) -> tuple[str, str | None]:
    """"Senior Analyst | Acme Bank" -> ("Senior Analyst", "Acme Bank")."""
    if not line:
        return "", None

    parts = [part.strip() for part in SEPARATOR_RE.split(line, maxsplit=1)]

    if len(parts) == 2 and parts[1]:
        return parts[0], parts[1]

    return line.strip(), None


LOCATION_TAIL_RE = re.compile(
    r"^(?:remote|[\w .'-]+,\s*[\w .'-]+|"
    + "|".join(re.escape(hint) for hint in ("uae", "united arab emirates", "philippines", "ksa", "qatar"))
    + r")$",
    re.I,
)


def _split_header(line: str) -> tuple[str, str | None, str | None]:
    """"Engineer - Acme Ltd - Dubai, UAE" -> role, employer, location.

    The third segment is only taken as a location when it actually looks like
    one; otherwise it stays part of the employer name (many companies have a
    dash in them).
    """
    if not line:
        return "", None, None

    parts = [part.strip() for part in SEPARATOR_RE.split(line) if part.strip()]

    if len(parts) == 1:
        return parts[0], None, None

    title = parts[0]
    rest = parts[1:]

    location = None
    if len(rest) > 1 and LOCATION_TAIL_RE.match(rest[-1]):
        location = rest.pop()

    company = " - ".join(rest) if rest else None

    return title, company, location


DEGREE_RE = re.compile(
    r"\b(bachelor|master|doctor|phd|ph\.d|bsc|b\.sc|ba\b|b\.a|msc|m\.sc|mba|"
    r"diploma|associate|certificate|high school|secondary)\b",
    re.I,
)


def _education(section: list[str]) -> list[EducationEntry]:
    entries: list[EducationEntry] = []

    for line in _join_degree_wraps(section):
        match = DATE_RANGE_RE.search(line)
        remainder = DATE_RANGE_RE.sub("", line) if match else line
        years = re.findall(r"\b(?:19|20)\d{2}\b", line)
        degree, institution = _split_role(BULLET_RE.sub("", remainder).strip(" |,-–—•\t"))

        if not degree:
            continue

        if match:
            start = _iso_month(match.group("start"))
            end = _iso_month(match.group("end"))
        else:
            # A single graduation year is common: "BSc Accounting, AUS — 2017".
            start = None
            end = years[-1] if years else None
            if end is not None:
                degree = degree.replace(end, "").strip(" |,-–—•\t")

        entries.append(
            EducationEntry(
                degree=degree,
                institution=institution,
                location=None,
                start_date=start,
                end_date=end,
            )
        )

    return entries


def _join_degree_wraps(section: list[str]) -> list[str]:
    """Rejoin an education entry split across two lines.

    "Bachelor of Science in IT" / "Our Lady of Lourdes College | 2020" is one
    qualification, not two. A degree line with no institution absorbs the
    following line when that line names no degree of its own.
    """
    joined: list[str] = []
    index = 0

    while index < len(section):
        line = section[index]
        following = section[index + 1] if index + 1 < len(section) else None

        wraps = (
            following is not None
            and DEGREE_RE.search(line) is not None
            and DEGREE_RE.search(following) is None
            and not SEPARATOR_RE.search(line)
            and DATE_RANGE_RE.search(line) is None
            and not re.search(r"\b(?:19|20)\d{2}\b", line)
        )

        if wraps:
            joined.append(f"{line} | {following}")
            index += 2
            continue

        joined.append(line)
        index += 1

    return joined


def _flat_items(section: list[str]) -> list[str]:
    """Skills/certifications appear as bullets, comma lists, or pipe lists."""
    items: list[str] = []

    for line in section:
        cleaned = BULLET_RE.sub("", line).strip()

        if not cleaned:
            continue

        parts = re.split(r"\s*[,;|•]\s*", cleaned) if re.search(r"[,;|•]", cleaned) else [cleaned]

        for part in parts:
            value = part.strip(" .-")
            if value and len(value) <= 80:
                items.append(value)

    return list(dict.fromkeys(items))


def _first(pattern: re.Pattern[str], text: str) -> str | None:
    match = pattern.search(text)
    return match.group(0).strip() if match else None


def _normalise_phone(raw: str) -> str:
    digits = re.sub(r"\D", "", raw)

    if raw.strip().startswith("00"):
        digits = digits[2:]

    return f"+{digits}" if digits else raw.strip()


def _iso_month(raw: str) -> str | None:
    """"Mar 2021" -> "2021-03"; "2021" -> "2021"; "Present" -> None."""
    if not raw or re.fullmatch(_PRESENT, raw.strip(), re.I):
        return None

    year_match = re.search(r"(19|20)\d{2}", raw)

    if not year_match:
        return None

    year = year_match.group(0)
    month_match = re.search(rf"({_MONTH_NAMES})", raw, re.I)

    if not month_match:
        return year

    return f"{year}-{MONTHS[month_match.group(1).lower()]:02d}"
