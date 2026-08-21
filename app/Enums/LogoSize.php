<?php

declare(strict_types=1);

namespace App\Enums;

enum LogoSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => __('Small'),
            self::Medium => __('Medium'),
            self::Large => __('Large'),
        };
    }

    /**
     * Rendered edge in pixels. The preview and the printed page use the same
     * value, so what the recruiter sees is what prints.
     */
    public function pixels(): int
    {
        return match ($this) {
            self::Small => 32,
            self::Medium => 48,
            self::Large => 72,
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
