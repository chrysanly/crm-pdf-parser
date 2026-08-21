<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

/**
 * The normalised result of parsing one PDF — the single shape the rest of the app
 * knows about, whichever parser produced it. Persisted in `resumes.parsed_data`
 * (shape documented in SCHEMA.md Part B).
 */
final readonly class ParsedResume
{
    /**
     * @param  list<DetailItem>  $details  "Personal Details" rows beyond the contact fields
     * @param  list<ExperienceEntry>  $experience
     * @param  list<EducationEntry>  $education
     * @param  list<SkillGroup>  $skillGroups  skills with their category labels
     * @param  list<string>  $skills  every skill, flattened (ATS keyword checks)
     * @param  list<string>  $certifications
     * @param  list<string>  $languages
     * @param  list<string>  $sectionOrder  the order the source document printed
     *                                      its sections in — read when building a
     *                                      template from a sample resume
     * @param  list<string>  $warnings  ATS issues found in the source document
     */
    public function __construct(
        public ContactInfo $contact,
        public ?string $headline,
        public array $sectionOrder,
        public array $details,
        public ?string $summary,
        public array $experience,
        public array $education,
        public array $skillGroups,
        public array $skills,
        public array $certifications,
        public array $languages,
        public array $warnings,
        public ?int $pageCount,
        public string $parserVersion,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var array<string, mixed> $contact */
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];

        $skillGroups = array_map(
            SkillGroup::fromArray(...),
            Cast::rowList($payload['skill_groups'] ?? []),
        );

        $flatSkills = Cast::stringList($payload['skills'] ?? []);

        // A parser that only sends grouped skills still gets a flat list, and one
        // that only sends a flat list still gets a single unlabelled group.
        if ($skillGroups === [] && $flatSkills !== []) {
            $skillGroups = [new SkillGroup(label: null, items: $flatSkills)];
        }

        if ($flatSkills === []) {
            $flatSkills = Cast::stringList(array_merge(
                ...array_map(static fn (SkillGroup $group): array => $group->items, $skillGroups),
            ));
        }

        return new self(
            contact: ContactInfo::fromArray($contact),
            headline: Cast::string($payload['headline'] ?? null),
            sectionOrder: Cast::stringList($payload['section_order'] ?? []),
            details: array_map(
                DetailItem::fromArray(...),
                Cast::rowList($payload['details'] ?? []),
            ),
            summary: Cast::string($payload['summary'] ?? null),
            experience: array_map(
                ExperienceEntry::fromArray(...),
                Cast::rowList($payload['experience'] ?? []),
            ),
            education: array_map(
                EducationEntry::fromArray(...),
                Cast::rowList($payload['education'] ?? []),
            ),
            skillGroups: $skillGroups,
            skills: $flatSkills,
            certifications: Cast::stringList($payload['certifications'] ?? []),
            languages: Cast::stringList($payload['languages'] ?? []),
            warnings: Cast::stringList($payload['warnings'] ?? []),
            pageCount: isset($payload['page_count']) && is_numeric($payload['page_count'])
                ? (int) $payload['page_count']
                : null,
            parserVersion: Cast::string($payload['parser_version'] ?? null) ?? 'unknown',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contact' => $this->contact->toArray(),
            'headline' => $this->headline,
            'section_order' => $this->sectionOrder,
            'details' => array_map(static fn (DetailItem $d): array => $d->toArray(), $this->details),
            'summary' => $this->summary,
            'experience' => array_map(static fn (ExperienceEntry $e): array => $e->toArray(), $this->experience),
            'education' => array_map(static fn (EducationEntry $e): array => $e->toArray(), $this->education),
            'skill_groups' => array_map(static fn (SkillGroup $g): array => $g->toArray(), $this->skillGroups),
            'skills' => $this->skills,
            'certifications' => $this->certifications,
            'languages' => $this->languages,
            'warnings' => $this->warnings,
            'page_count' => $this->pageCount,
            'parser_version' => $this->parserVersion,
        ];
    }
}
