<?php

declare(strict_types=1);

namespace App\Actions\Resume;

use App\Contracts\ResumeParser;
use App\Enums\ResumeStatus;
use App\Exceptions\ResumeParsingFailedException;
use App\Models\Resume;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * Runs one resume through the parser and persists the normalised result.
 * Called from ParseResumeJob — never from a request (RULES §6.6).
 */
final readonly class ParseResume
{
    public function __construct(
        private ResumeParser $parser,
        private DatabaseManager $db,
        private LoggerInterface $logger,
    ) {}

    public function handle(Resume $resume): Resume
    {
        $resume->update([
            'status' => ResumeStatus::Processing,
            'failure_reason' => null,
        ]);

        try {
            $parsed = $this->parser->parse($resume->stored_path, $resume->original_filename);
        } catch (ResumeParsingFailedException $exception) {
            // PII stays out of the log: identifiers only (RULES §5, SCHEMA §A7).
            $this->logger->warning('resume.parse_failed', [
                'resume_id' => $resume->public_id,
                'company_id' => $resume->company_id,
                'reason' => $exception->getMessage(),
            ]);

            $resume->update([
                'status' => ResumeStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->db->transaction(function () use ($resume, $parsed): void {
            $resume->update([
                'status' => ResumeStatus::Parsed,
                'candidate_name' => $parsed->contact->fullName,
                'candidate_email' => $parsed->contact->email,
                'page_count' => $parsed->pageCount,
                'parsed_data' => $parsed->toArray(),
                'failure_reason' => null,
                'parsed_at' => now(),
            ]);
        });

        $this->logger->info('resume.parsed', [
            'resume_id' => $resume->public_id,
            'company_id' => $resume->company_id,
            'parser_version' => $parsed->parserVersion,
        ]);

        return $resume->refresh();
    }
}
