<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Resume\ParseResume;
use App\Enums\ResumeStatus;
use App\Models\Resume;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Parsing is a slow, third-party-dependent operation, so it never runs in a
 * request (RULES §6.6). Takes the id, not the model, so a stale payload can't
 * overwrite fresher state.
 */
final class ParseResumeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function __construct(public readonly int $resumeId) {}

    public function handle(ParseResume $action): void
    {
        $resume = Resume::find($this->resumeId);

        if ($resume === null || $resume->status === ResumeStatus::Parsed) {
            return;
        }

        $action->handle($resume);
    }

    public function failed(Throwable $exception): void
    {
        Resume::where('id', $this->resumeId)
            ->where('status', '!=', ResumeStatus::Parsed)
            ->update([
                'status' => ResumeStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ]);
    }
}
