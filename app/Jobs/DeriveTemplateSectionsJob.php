<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ResumeTemplate\DeriveTemplateSections;
use App\Enums\ResumeStatus;
use App\Models\ResumeTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reading the sample PDF means calling the sidecar, so it never happens in a
 * request (RULES §6.6). Takes the id, not the model, so a stale payload can't
 * overwrite fresher state.
 */
final class DeriveTemplateSectionsJob implements ShouldQueue
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

    public function __construct(public readonly int $templateId) {}

    public function handle(DeriveTemplateSections $action): void
    {
        $template = ResumeTemplate::find($this->templateId);

        if ($template === null || $template->sample_path === null) {
            return;
        }

        $action->handle($template);
    }

    public function failed(Throwable $exception): void
    {
        ResumeTemplate::where('id', $this->templateId)
            ->where('sample_status', '!=', ResumeStatus::Parsed)
            ->update([
                'sample_status' => ResumeStatus::Failed,
                'sample_failure_reason' => $exception->getMessage(),
            ]);
    }
}
