<?php

declare(strict_types=1);

namespace App\Enums;

enum ResumeStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Parsed = 'parsed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Queued'),
            self::Processing => __('Parsing'),
            self::Parsed => __('Parsed'),
            self::Failed => __('Failed'),
        };
    }

    /**
     * Token name consumed by the frontend StatusBadge (DESIGN.md §5).
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Processing => 'info',
            self::Parsed => 'success',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Parsed || $this === self::Failed;
    }
}
