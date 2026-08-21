"""Response schema — the contract with App\\DTOs\\Parsing\\ParsedResume.

Field names here are the JSON keys Laravel reads. Changing one is a breaking
change on both sides: update the PHP DTO in the same commit.
"""

from __future__ import annotations

from pydantic import BaseModel, Field

PARSER_VERSION = "sidecar-1.0"


class Contact(BaseModel):
    full_name: str | None = None
    email: str | None = None
    phone: str | None = None
    location: str | None = None
    linkedin: str | None = None
    website: str | None = None


class ExperienceEntry(BaseModel):
    title: str
    company: str | None = None
    location: str | None = None
    start_date: str | None = None  # "YYYY-MM" or "YYYY"
    end_date: str | None = None
    is_current: bool = False
    highlights: list[str] = Field(default_factory=list)


class EducationEntry(BaseModel):
    degree: str
    institution: str | None = None
    location: str | None = None
    start_date: str | None = None
    end_date: str | None = None


class DetailItem(BaseModel):
    """One row of a "Personal Details" block: "Date of Birth: June 24, 1997"."""

    label: str
    value: str


class SkillGroup(BaseModel):
    """A labelled skills line: "Languages & Frameworks: PHP, JavaScript, ...".

    `label` is None for resumes that list skills without categories, so a flat
    list is just a single unlabelled group.
    """

    label: str | None = None
    items: list[str] = Field(default_factory=list)


class ParsedResume(BaseModel):
    contact: Contact = Field(default_factory=Contact)
    summary: str | None = None
    experience: list[ExperienceEntry] = Field(default_factory=list)
    education: list[EducationEntry] = Field(default_factory=list)
    skills: list[str] = Field(default_factory=list)
    certifications: list[str] = Field(default_factory=list)
    languages: list[str] = Field(default_factory=list)
    warnings: list[str] = Field(default_factory=list)
    page_count: int | None = None
    parser_version: str = PARSER_VERSION
