<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;
use Throwable;

/**
 * Carries a user-safe message (ARCHITECTURE §7) — never a stack trace or an
 * upstream response body.
 */
final class ResumeParsingFailedException extends DomainException
{
    public static function unreachable(?Throwable $previous = null): self
    {
        return new self(
            __('The resume parser is unavailable right now. The upload was saved and can be retried.'),
            previous: $previous,
        );
    }

    public static function rejected(string $reason): self
    {
        return new self(
            __('This document could not be parsed: :reason', ['reason' => $reason]),
        );
    }

    public static function unreadable(): self
    {
        return new self(
            __('No readable text was found in this PDF. Scanned images are not supported yet.'),
        );
    }
}
