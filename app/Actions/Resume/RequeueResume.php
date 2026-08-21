<?php

declare(strict_types=1);

namespace App\Actions\Resume;

use App\Enums\ResumeStatus;
use App\Jobs\ParseResumeJob;
use App\Models\Resume;
use Illuminate\Database\DatabaseManager;

/**
 * Sends an already-processed document back through the parser.
 *
 * ParseResumeJob deliberately skips resumes that are already `parsed` (a queue
 * retry must not redo work), so a re-parse has to reset the status first —
 * otherwise the button looks like it worked and nothing happens.
 */
final readonly class RequeueResume
{
    public function __construct(private DatabaseManager $db) {}

    public function handle(Resume $resume): Resume
    {
        $this->db->transaction(function () use ($resume): void {
            $resume->update([
                'status' => ResumeStatus::Pending,
                'failure_reason' => null,
            ]);
        });

        ParseResumeJob::dispatch($resume->id);

        return $resume->refresh();
    }
}
