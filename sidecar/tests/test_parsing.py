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


# The layout below is the one that produced phantom jobs in production: the role
# header sits on its own line, its dates on the next, and long bullets wrap.
WRAPPED_RESUME = """Chris Roma
chris@example.com | +971529258013

EXPERIENCE
Senior Full-Stack Developer - Almutakamela Vehicle Testing and Registration - Dubai, UAE
Nov 2025 - Present
• Led development of a modular ERP system in Laravel spanning four core modules: Dashboard
and API App.
• Implemented role-based middleware for authentication and permission management, securing
modules by user role.
Senior PHP Developer - OmniQuest PH (Unilab) - Philippines
Mar 2025 - Aug 2025
• Managed a single project comprising four interconnected systems integrated with a central
data flow and functionality across all platforms.

EDUCATION
Bachelor of Science in Information Technology
Our Lady of Lourdes College, Valenzuela City | 2020
"""


def test_dates_on_their_own_line_attach_to_the_header_above():
    resume = parse(WRAPPED_RESUME)

    assert len(resume.experience) == 2

    first = resume.experience[0]
    assert first.title == "Senior Full-Stack Developer"
    assert first.company == "Almutakamela Vehicle Testing and Registration"
    assert first.location == "Dubai, UAE"
    assert first.start_date == "2025-11"
    assert first.is_current is True

    second = resume.experience[1]
    assert second.title == "Senior PHP Developer"
    assert second.company == "OmniQuest PH (Unilab)"
    assert second.start_date == "2025-03"
    assert second.end_date == "2025-08"


def test_wrapped_bullets_are_rejoined_and_never_become_jobs():
    resume = parse(WRAPPED_RESUME)

    titles = [entry.title for entry in resume.experience]
    assert "" not in titles
    assert all(entry.title for entry in resume.experience)

    highlights = resume.experience[0].highlights
    assert len(highlights) == 2
    assert highlights[0].endswith("Dashboard and API App.")
    assert highlights[1].endswith("securing modules by user role.")


def test_a_degree_wrapped_onto_two_lines_is_one_qualification():
    resume = parse(WRAPPED_RESUME)

    assert len(resume.education) == 1
    assert resume.education[0].degree == "Bachelor of Science in Information Technology"
    assert resume.education[0].institution is not None
    assert "Our Lady of Lourdes College" in resume.education[0].institution
    assert resume.education[0].end_date == "2020"


DETAILED_RESUME = """Chrysanly John C. Roma
Senior Full-Stack Developer

PERSONAL DETAILS
Phone number: +971 52 925 8013
Email: chrys.romao21@gmail.com
Date of Birth: June 24, 1997
Civil Status: Single
Language: Filipino & English
Portfolio: https://chrysanly.github.io/portfolio_v2/

TECHNICAL SKILLS
Languages & Frameworks: PHP, JavaScript, Laravel 7-12, Node.js, Vue 3, Angular
7-10, .NET Web API
Databases: MySQL, PostgreSQL
Architecture & Practices: SOLID Principles, RESTful API Design, Role-Based
Access Control (RBAC), TDD
"""


def test_the_job_title_under_the_name_becomes_the_headline():
    resume = parse(DETAILED_RESUME)

    assert resume.contact.full_name == "Chrysanly John C. Roma"
    assert resume.headline == "Senior Full-Stack Developer"


def test_personal_details_are_captured_without_duplicating_contact_fields():
    resume = parse(DETAILED_RESUME)

    labels = {detail.label: detail.value for detail in resume.details}

    assert labels["Date of Birth"] == "June 24, 1997"
    assert labels["Civil Status"] == "Single"
    # Phone/email have dedicated contact fields, so they are not repeated here.
    assert "Phone number" not in labels
    assert "Email" not in labels
    assert resume.contact.phone == "+971529258013"
    assert resume.contact.email == "chrys.romao21@gmail.com"


def test_languages_fall_back_to_the_details_block():
    resume = parse(DETAILED_RESUME)

    assert resume.languages == ["Filipino", "English"]


def test_skills_keep_their_category_labels():
    resume = parse(DETAILED_RESUME)

    labels = [group.label for group in resume.skill_groups]

    assert labels == ["Languages & Frameworks", "Databases", "Architecture & Practices"]
    assert resume.skill_groups[1].items == ["MySQL", "PostgreSQL"]


def test_a_skill_split_across_a_line_break_stays_one_item():
    resume = parse(DETAILED_RESUME)

    frameworks = resume.skill_groups[0].items
    practices = resume.skill_groups[2].items

    assert "Angular 7-10" in frameworks
    assert "Angular" not in frameworks
    assert "Role-Based Access Control (RBAC)" in practices


def test_the_flat_skill_list_still_mirrors_every_group():
    resume = parse(DETAILED_RESUME)

    expected = [item for group in resume.skill_groups for item in group.items]

    assert resume.skills == expected


def test_an_uncategorised_skills_list_becomes_one_unlabelled_group():
    resume = parse(RESUME)

    assert [group.label for group in resume.skill_groups] == [None]
    assert "Power BI" in resume.skill_groups[0].items


def test_page_count_and_warnings_pass_through():
    resume = parse(RESUME, page_count=3, warnings=("two-column layout",))

    assert resume.page_count == 3
    assert "two-column layout" in resume.warnings
