<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a company's logo sits in the ATS resume header. Saved per company so a
 * client's letterhead is consistent across every resume produced for them.
 */
enum LogoPlacement: string
{
    case Hidden = 'hidden';
    case Left = 'left';
    case Centre = 'centre';
    case Right = 'right';

    public function label(): string
    {
        return match ($this) {
            self::Hidden => __('No logo'),
            self::Left => __('Left of the name'),
            self::Centre => __('Centred above the name'),
            self::Right => __('Right of the name'),
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
