<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Archiving a template that companies still point at would leave them without a
 * house style, so it is refused at the Action boundary as well as the Policy.
 */
final class ResumeTemplateInUseException extends RuntimeException
{
    public static function forCompanies(int $count): self
    {
        return new self("The template is still assigned to {$count} company(ies).");
    }
}
