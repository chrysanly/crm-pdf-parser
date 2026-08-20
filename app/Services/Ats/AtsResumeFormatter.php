<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\DTOs\Parsing\EducationEntry;
use App\DTOs\Parsing\ExperienceEntry;
use App\DTOs\Parsing\ParsedResume;
use App\Models\Company;

/**
 * Maps a ParsedResume onto one company's house style: section order, section
 * labels, ATS-safe text normalisation, and an ATS score with actionable notes.
 *
 * Pure and stateless — the single source of truth for "what the ATS resume looks
 * like". The React template only renders what this returns (DESIGN.md §1.4).
 */
final readonly class AtsResumeFormatter
{
    /**
     * Section keys this formatter knows how to emit.
     */
    private const array SUPPORTED_SECTIONS = [
        'summary',
        'experience',
        'education',
        'skills',
        'certifications',
        'languages',
    ];

    /**
     * @return array{
     *     header: array<string, mixed>,
     *     sections: list<array<string, mixed>>,
     *     score: array{value: int, band: string, notes: list<string>},
     *     warnings: list<string>,
     *     template: string,
     * }
     */
    public function format(ParsedResume $resume, Company $company): array
    {
        $sections = [];

        foreach ($this->sectionOrderFor($company) as $key) {
            $section = $this->section($key, $resume);

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return [
            'header' => $this->header($resume),
            'sections' => $sections,
            'score' => $this->score($resume),
            'warnings' => $resume->warnings,
            'template' => $company->resume_template->value,
        ];
    }

    /**
     * @return list<string>
     */
    private function sectionOrderFor(Company $company): array
    {
        return array_values(array_filter(
            $company->effectiveSectionOrder(),
            static fn (string $key): bool => in_array($key, self::SUPPORTED_SECTIONS, true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function header(ParsedResume $resume): array
    {
        $contact = $resume->contact;

        return [
            'name' => $contact->fullName ?? __('Name not detected'),
            'contact_lines' => array_values(array_filter([
                $contact->email,
                $contact->phone,
                $contact->location,
                $contact->linkedin,
                $contact->website,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>|null  null when the section has no content to show
     */
    private function section(string $key, ParsedResume $resume): ?array
    {
        return match ($key) {
            'summary' => $resume->summary === null ? null : [
                'key' => 'summary',
                'label' => __('Professional Summary'),
                'type' => 'text',
                'text' => $this->normalise($resume->summary),
            ],
            'experience' => $resume->experience === [] ? null : [
                'key' => 'experience',
                'label' => __('Professional Experience'),
                'type' => 'timeline',
                'entries' => array_map($this->experienceEntry(...), $resume->experience),
            ],
            'education' => $resume->education === [] ? null : [
                'key' => 'education',
                'label' => __('Education'),
                'type' => 'timeline',
                'entries' => array_map($this->educationEntry(...), $resume->education),
            ],
            'skills' => $resume->skills === [] ? null : [
                'key' => 'skills',
                'label' => __('Core Skills'),
                'type' => 'tags',
                'items' => $resume->skills,
            ],
            'certifications' => $resume->certifications === [] ? null : [
                'key' => 'certifications',
                'label' => __('Certifications'),
                'type' => 'list',
                'items' => $resume->certifications,
            ],
            'languages' => $resume->languages === [] ? null : [
                'key' => 'languages',
                'label' => __('Languages'),
                'type' => 'list',
                'items' => $resume->languages,
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function experienceEntry(ExperienceEntry $entry): array
    {
        return [
            'primary' => $entry->title,
            'secondary' => $entry->company,
            'location' => $entry->location,
            'period' => $this->period($entry->startDate, $entry->endDate, $entry->isCurrent),
            'highlights' => array_map($this->normalise(...), $entry->highlights),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function educationEntry(EducationEntry $entry): array
    {
        return [
            'primary' => $entry->degree,
            'secondary' => $entry->institution,
            'location' => $entry->location,
            'period' => $this->period($entry->startDate, $entry->endDate, false),
            'highlights' => [],
        ];
    }

    private function period(?string $start, ?string $end, bool $isCurrent): string
    {
        $from = $this->humanMonth($start);
        $to = $isCurrent ? __('Present') : $this->humanMonth($end);

        if ($from === null && $to === null) {
            return '';
        }

        if ($from === null) {
            return (string) $to;
        }

        return $to === null ? $from : $from.' — '.$to;
    }

    private function humanMonth(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches) !== 1) {
            return $value;
        }

        $timestamp = mktime(0, 0, 0, (int) $matches[2], 1, (int) $matches[1]);

        return $timestamp === false ? $value : date('M Y', $timestamp);
    }

    /**
     * ATS-safe text: collapse whitespace, strip decorative bullets and smart quotes
     * that break keyword matching in applicant tracking systems.
     */
    private function normalise(string $text): string
    {
        $clean = str_replace(
            ['•', '●', '▪', '·', "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}"],
            ['', '', '', '', "'", "'", '"', '"', '-', '-'],
            $text,
        );

        return trim((string) preg_replace('/\s+/u', ' ', $clean));
    }

    /**
     * @return array{value: int, band: string, notes: list<string>}
     */
    private function score(ParsedResume $resume): array
    {
        $notes = [];
        $value = 100;

        if ($resume->contact->email === null) {
            $value -= 20;
            $notes[] = __('No email address was detected — ATS filters usually reject the file outright.');
        }

        if ($resume->contact->phone === null) {
            $value -= 10;
            $notes[] = __('No phone number was detected.');
        }

        if ($resume->summary === null) {
            $value -= 10;
            $notes[] = __('No professional summary — add 2–3 lines with the target job title.');
        }

        if ($resume->experience === []) {
            $value -= 25;
            $notes[] = __('No work experience could be extracted; the layout may be unreadable to an ATS.');
        }

        if (count($resume->skills) < 5) {
            $value -= 10;
            $notes[] = __('Fewer than five skills detected — ATS keyword matching needs an explicit skills list.');
        }

        $quantified = array_filter(
            $resume->experience,
            static fn (ExperienceEntry $entry): bool => array_any(
                $entry->highlights,
                static fn (string $highlight): bool => preg_match('/\d/', $highlight) === 1,
            ),
        );

        if ($resume->experience !== [] && $quantified === []) {
            $value -= 5;
            $notes[] = __('No quantified achievements found — add numbers (%, AED, headcount) to highlights.');
        }

        $value = max(0, min(100, $value));

        return [
            'value' => $value,
            'band' => match (true) {
                $value >= 85 => 'strong',
                $value >= 65 => 'fair',
                default => 'weak',
            },
            'notes' => array_values($notes),
        ];
    }
}
