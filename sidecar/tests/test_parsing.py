"""Parser unit tests: plain text in, structured resume out."""

from app.parsing import parse

RESUME = """Layla Haddad
Dubai, United Arab Emirates | layla.haddad@example.com | +971 50 123 4567
linkedin.com/in/layla-haddad

PROFESSIONAL SUMMARY
Operations manager with 8 years in regional logistics, leading cross-border
fulfilment for GCC retail accounts.

WORK EXPERIENCE
Regional Operations Manager | Gulf Freight Partners    Mar 2021 - Present
- Owned a 42-person warehouse and last-mile team across three emirates.
- Cut cost per delivered order by 22% by renegotiating 3PL contracts.
Logistics Supervisor | Almasa Retail Group    Jan 2018 - Feb 2021
- Scheduled inbound freight for 60+ retail outlets.

EDUCATION
BSc Supply Chain Management | American University of Sharjah    2013 - 2017

SKILLS
Supply chain planning, Vendor negotiation, SAP MM, Power BI, Team leadership

CERTIFICATIONS
- APICS CSCP (2022)
- Lean Six Sigma Green Belt

LANGUAGES
Arabic - native, English - fluent
"""


def test_contact_details_are_extracted():
    resume = parse(RESUME)

    assert resume.contact.full_name == "Layla Haddad"
    assert resume.contact.email == "layla.haddad@example.com"
    assert resume.contact.phone == "+971501234567"
    assert resume.contact.location is not None
    assert "Dubai" in resume.contact.location
    assert resume.contact.linkedin == "linkedin.com/in/layla-haddad"


def test_summary_is_joined_into_one_paragraph():
    resume = parse(RESUME)

    assert resume.summary is not None
    assert resume.summary.startswith("Operations manager with 8 years")
    assert "\n" not in resume.summary


def test_experience_entries_keep_order_dates_and_highlights():
    resume = parse(RESUME)

    assert len(resume.experience) == 2

    first = resume.experience[0]
    assert first.title == "Regional Operations Manager"
    assert first.company == "Gulf Freight Partners"
    assert first.start_date == "2021-03"
    assert first.end_date is None
    assert first.is_current is True
    assert len(first.highlights) == 2

    second = resume.experience[1]
    assert second.is_current is False
    assert second.start_date == "2018-01"
    assert second.end_date == "2021-02"


def test_education_is_extracted():
    resume = parse(RESUME)

    assert len(resume.education) == 1
    assert resume.education[0].degree == "BSc Supply Chain Management"
    assert resume.education[0].institution == "American University of Sharjah"


def test_comma_separated_skills_are_split():
    resume = parse(RESUME)

    assert "Supply chain planning" in resume.skills
    assert "Power BI" in resume.skills
    assert len(resume.skills) == 5


def test_bulleted_certifications_lose_their_bullets():
    resume = parse(RESUME)

    assert resume.certifications == ["APICS CSCP (2022)", "Lean Six Sigma Green Belt"]


def test_an_open_end_date_is_reported_as_a_warning():
    resume = parse(RESUME)

    assert any("Present" in warning for warning in resume.warnings)


def test_a_missing_email_is_reported():
    resume = parse("Some Person\nNo contact details here.\n")

    assert resume.contact.email is None
    assert any("email" in warning for warning in resume.warnings)


def test_alternative_headings_are_recognised():
    resume = parse(
        "Ali Nasser\nali@example.com\n\n"
        "PROFILE\nSoftware engineer.\n\n"
        "EMPLOYMENT HISTORY\nBackend Engineer @ Tashkeel    2019 - 2023\n\n"
        "KEY SKILLS\nPython, Go\n"
    )

    assert resume.summary == "Software engineer."
    assert resume.experience[0].company == "Tashkeel"
    assert resume.skills == ["Python", "Go"]


def test_page_count_and_warnings_pass_through():
    resume = parse(RESUME, page_count=3, warnings=("two-column layout",))

    assert resume.page_count == 3
    assert "two-column layout" in resume.warnings
