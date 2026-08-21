<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\DTOs\Parsing\DetailItem;
use App\DTOs\Parsing\EducationEntry;
use App\DTOs\Parsing\ExperienceEntry;
use App\DTOs\Parsing\ParsedResume;
use App\DTOs\Parsing\SkillGroup;
use App\Enums\LogoPlacement;
use App\Enums\TemplateLayout;
use App\Models\Company;
use App\Models\ResumeTemplate;

/**
 * Maps a ParsedResume onto one company's house style: section order, section
 * labels, ATS-safe text normalisation, and an ATS score with actionable notes.
 *
 * Pure and stateless — the single source of truth for "what the ATS resume looks
 * like". The React template only renders what this returns (DESIGN.md §1.4).
 */
final readonly class AtsResumeFormatter
{
    public function __construct(private AtsScore $scores) {}

    /**
     * Section keys this formatter knows how to emit — the set a template may
     * order, so template validation reads it from here rather than repeating it.
     */
    public const array SUPPORTED_SECTIONS = [
        'details',
        'summary',
        'experience',
        'education',
        'skills',
        'certifications',
        'languages',
    ];

    /**
     * @param  ResumeTemplate|null  $template  the style the document was produced
     *                                         with; null falls back to the company's current template
     * @return array{
     *     header: array<string, mixed>,
     *     sections: list<array<string, mixed>>,
     *     score: array{value: int, band: string, notes: list<string>},
     *     warnings: list<string>,
     *     template: string,
     *     template_name: string,
     * }
     */
    public function format(ParsedResume $resume, Company $company, ?ResumeTemplate $template = null): array
    {
        $template ??= $company->resumeTemplate;
        $layout = $template->layout;
        $sections = [];

        foreach ($this->sectionOrderFor($template) as $key) {
            $section = $this->section($key, $resume);

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return [
            'header' => $this->header($resume, $company, $layout),
            'sections' => $sections,
            'score' => $this->scores->for($resume),
            'warnings' => $resume->warnings,
            'template' => $layout->value,
            'template_name' => $template->name,
        ];
    }

    /**
     * @return list<string>
     */
    private function sectionOrderFor(ResumeTemplate $template): array
    {
        return array_values(array_filter(
            $template->effectiveSectionOrder(),
            static fn (string $key): bool => in_array($key, self::SUPPORTED_SECTIONS, true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function header(ParsedResume $resume, Company $company, TemplateLayout $layout): array
    {
        $contact = $resume->contact;

        return [
            'name' => $contact->fullName ?? $this->label('Name not detected'),
            'headline' => $resume->headline,
            'contact_lines' => array_values(array_filter([
                $contact->email,
                $contact->phone,
                $contact->location,
                $contact->linkedin,
                $contact->website,
            ])),
            'centred' => $layout->hasCentredHeader(),
            'logo' => $this->logo($company),
        ];
    }

    /**
     * Letterhead settings, resolved server-side so the preview and the printed
     * page agree (DESIGN §1.4).
     *
     * @return array{placement: string, size: string, pixels: int}|null
     */
    private function logo(Company $company): ?array
    {
        if ($company->logo_path === null || $company->logo_placement === LogoPlacement::Hidden) {
            return null;
        }

        return [
            'placement' => $company->logo_placement->value,
            'size' => $company->logo_size->value,
            'pixels' => $company->logo_size->pixels(),
        ];
    }

    /**
     * @return array<string, mixed>|null null when the section has no content to show
     */
    private function section(string $key, ParsedResume $resume): ?array
    {
        return match ($key) {
            'details' => $resume->details === [] ? null : [
                'key' => 'details',
                'label' => $this->label('Personal Details'),
                'type' => 'details',
                'rows' => array_map(
                    static fn (DetailItem $detail): array => [
                        'label' => $detail->label,
                        'value' => $detail->value,
                    ],
                    $resume->details,
                ),
            ],
            'summary' => $resume->summary === null ? null : [
                'key' => 'summary',
                'label' => $this->label('Professional Summary'),
                'type' => 'text',
                'text' => $this->normalise($resume->summary),
            ],
            'experience' => $resume->experience === [] ? null : [
                'key' => 'experience',
                'label' => $this->label('Professional Experience'),
                'type' => 'timeline',
                'entries' => array_map($this->experienceEntry(...), $resume->experience),
            ],
            'education' => $resume->education === [] ? null : [
                'key' => 'education',
                'label' => $this->label('Education'),
                'type' => 'timeline',
                'entries' => array_map($this->educationEntry(...), $resume->education),
            ],
            // Grouped skills keep their printed categories; an unlabelled single
            // group renders as a plain tag cloud.
            'skills' => $resume->skillGroups === [] ? null : [
                'key' => 'skills',
                'label' => $this->label('Technical Skills'),
                'type' => $this->skillsAreGrouped($resume) ? 'skill_groups' : 'tags',
                'items' => $resume->skills,
                'groups' => array_map(
                    static fn (SkillGroup $group): array => [
                        'label' => $group->label,
                        'items' => $group->items,
                    ],
                    $resume->skillGroups,
                ),
            ],
            'certifications' => $resume->certifications === [] ? null : [
                'key' => 'certifications',
                'label' => $this->label('Certifications'),
                'type' => 'list',
                'items' => $resume->certifications,
            ],
            'languages' => $resume->languages === [] ? null : [
                'key' => 'languages',
                'label' => $this->label('Languages'),
                'type' => 'list',
                'items' => $resume->languages,
            ],
            default => null,
        };
    }

    private function skillsAreGrouped(ParsedResume $resume): bool
    {
        foreach ($resume->skillGroups as $group) {
            if ($group->label !== null) {
                return true;
            }
        }

        return false;
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

    /**
     * `__()` is typed as array|string because a translation key can resolve to an
     * array. These keys are always single strings, so narrow once, here.
     */
    private function label(string $key): string
    {
        $translated = __($key);

        return is_string($translated) ? $translated : $key;
    }

    private function period(?string $start, ?string $end, bool $isCurrent): string
    {
        $from = $this->humanMonth($start);
        $to = $isCurrent ? $this->label('Present') : $this->humanMonth($end);

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
}
