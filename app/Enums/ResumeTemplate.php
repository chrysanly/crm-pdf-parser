<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The company's resume house-style. Drives section order and the React template
 * used to render the ATS output (see AtsResumeFormatter).
 */
enum ResumeTemplate: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Compact = 'compact';

    public function label(): string
    {
        return match ($this) {
            self::Classic => __('Classic — chronological, single column'),
            self::Modern => __('Modern — summary-led with skills band'),
            self::Compact => __('Compact — one page, dense'),
        };
    }

    /**
     * Default section order for the template. A company may override it with
     * its own `section_order` column; the formatter falls back to this.
     *
     * @return list<string>
     */
    public function defaultSectionOrder(): array
    {
        return match ($this) {
            self::Classic => ['summary', 'experience', 'education', 'skills', 'certifications', 'languages'],
            self::Modern => ['summary', 'skills', 'experience', 'certifications', 'education', 'languages'],
            self::Compact => ['summary', 'skills', 'experience', 'education'],
        };
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
