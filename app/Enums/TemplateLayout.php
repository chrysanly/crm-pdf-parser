<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The rendering engines the ATS preview ships with. A ResumeTemplate row names
 * one of these as its base layout and adds its own section order on top, so a
 * new house style is data, not code — a new *layout* is code, and belongs here.
 */
enum TemplateLayout: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Compact = 'compact';
    case Professional = 'professional';

    public function label(): string
    {
        return match ($this) {
            self::Classic => __('Classic — chronological, single column'),
            self::Modern => __('Modern — summary-led with skills band'),
            self::Compact => __('Compact — one page, dense'),
            self::Professional => __('Professional — centred header, ruled sections'),
        };
    }

    /**
     * Default section order for the layout. A template may override it with its
     * own `section_order` column; the formatter falls back to this.
     *
     * @return list<string>
     */
    public function defaultSectionOrder(): array
    {
        return match ($this) {
            self::Classic => ['summary', 'experience', 'education', 'skills', 'certifications', 'languages'],
            self::Modern => ['summary', 'skills', 'experience', 'certifications', 'education', 'languages'],
            self::Compact => ['summary', 'skills', 'experience', 'education'],
            // Mirrors the reference document: details, summary, skills, experience,
            // then education.
            self::Professional => ['details', 'summary', 'skills', 'experience', 'certifications', 'education', 'languages'],
        };
    }

    /**
     * Layouts that print a centred name + job-title header and ruled section
     * rules, rather than a left-aligned header.
     */
    public function hasCentredHeader(): bool
    {
        return $this === self::Professional;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
