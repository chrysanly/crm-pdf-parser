<?php

declare(strict_types=1);

namespace App\Actions\ResumeTemplate;

use App\DTOs\ResumeTemplateData;
use App\Enums\ResumeStatus;
use App\Jobs\DeriveTemplateSectionsJob;
use App\Models\ResumeTemplate;
use App\Services\Storage\TemplateSampleStorage;
use Illuminate\Database\DatabaseManager;
use Throwable;

final readonly class CreateResumeTemplate
{
    public function __construct(
        private DatabaseManager $db,
        private UniqueResumeTemplateSlug $slugs,
        private TemplateSampleStorage $samples,
    ) {}

    public function handle(ResumeTemplateData $data): ResumeTemplate
    {
        $template = $this->db->transaction(fn (): ResumeTemplate => ResumeTemplate::create([
            ...$data->toAttributes(),
            'slug' => $this->slugs->for($data->name),
        ]));

        if ($data->sampleResume === null) {
            return $template;
        }

        try {
            $template->update([
                'sample_path' => $this->samples->store($data->sampleResume, $template->public_id),
                'sample_filename' => $data->sampleResume->getClientOriginalName(),
                'sample_status' => ResumeStatus::Pending,
                'sample_failure_reason' => null,
            ]);
        } catch (Throwable $exception) {
            // The template itself is valid; only the sample failed to land.
            $template->update([
                'sample_status' => ResumeStatus::Failed,
                'sample_failure_reason' => $exception->getMessage(),
            ]);

            return $template->refresh();
        }

        // Reading the PDF means calling the sidecar, so it happens on the queue.
        DeriveTemplateSectionsJob::dispatch($template->id);

        return $template->refresh();
    }
}
