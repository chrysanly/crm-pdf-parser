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
     * @param  list<ExperienceEntry>  $experience
     * @param  list<EducationEntry>  $education
     * @param  list<string>  $skills
     * @param  list<string>  $certifications
     * @param  list<string>  $languages
     * @param  list<string>  $warnings  ATS issues found in the source document
     */
    public function __construct(
        public ContactInfo $contact,
        public ?string $summary,
        public array $experience,
        public array $education,
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

        return new self(
            contact: ContactInfo::fromArray($contact),
            summary: Cast::string($payload['summary'] ?? null),
            experience: array_map(
                ExperienceEntry::fromArray(...),
                Cast::rowList($payload['experience'] ?? []),
            ),
            education: array_map(
                EducationEntry::fromArray(...),
                Cast::rowList($payload['education'] ?? []),
            ),
            skills: Cast::stringList($payload['skills'] ?? []),
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
            'summary' => $this->summary,
            'experience' => array_map(static fn (ExperienceEntry $e): array => $e->toArray(), $this->experience),
            'education' => array_map(static fn (EducationEntry $e): array => $e->toArray(), $this->education),
            'skills' => $this->skills,
            'certifications' => $this->certifications,
            'languages' => $this->languages,
            'warnings' => $this->warnings,
            'page_count' => $this->pageCount,
            'parser_version' => $this->parserVersion,
        ];
    }
}
