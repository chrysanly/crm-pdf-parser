<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\Parsing\ParsedResume;
use App\Exceptions\ResumeParsingFailedException;

/**
 * The only boundary between Laravel and the Python parsing sidecar (RULES §3-D).
 * Implementations: SidecarResumeParser (HTTP) and FakeResumeParser (tests/local).
 */
interface ResumeParser
{
    /**
     * @param  string  $storedPath  path on the private disk
     * @param  string  $originalFilename  used only for the multipart filename
     *
     * @throws ResumeParsingFailedException
     */
    public function parse(string $storedPath, string $originalFilename): ParsedResume;
}
