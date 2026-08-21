<?php

declare(strict_types=1);

namespace App\Actions\ResumeTemplate;

use App\DTOs\ResumeTemplateData;
use App\Enums\ResumeStatus;
use App\Jobs\DeriveTemplateSectionsJob;
use App\Models\ResumeTemplate;
use App\Services\Storage\TemplateSampleStorage;
use Illuminate\Database\DatabaseManager;

final readonly class UpdateResumeTemplate
{
    public function __construct(
        private DatabaseManager $db,
        private UniqueResumeTemplateSlug $slugs,
        private TemplateSampleStorage $samples,
    ) {}

    public function handle(ResumeTemplate $template, ResumeTemplateData $data): ResumeTemplate
    {
        $previousSample = $template->sample_path;

        $sample = match (true) {
            $data->sampleResume !== null => $this->samples->store($data->sampleResume, $template->public_id),
            $data->removeSample => null,
            default => $previousSample,
        };

        $this->db->transaction(function () use ($template, $data, $sample): void {
            $template->fill($data->toAttributes());

            // Renaming re-slugs it; the old slug is released.
            if ($template->isDirty('name')) {
                $template->slug = $this->slugs->for($data->name, $template->id);
            }

            $template->sample_path = $sample;

            if ($data->sampleResume !== null) {
                $template->sample_filename = $data->sampleResume->getClientOriginalName();
                $template->sample_status = ResumeStatus::Pending;
                $template->sample_failure_reason = null;
            }

            if ($sample === null) {
                $template->sample_filename = null;
                $template->sample_status = null;
                $template->sample_failure_reason = null;
            }

            $template->save();
        });

        if ($sample !== $previousSample) {
            $this->samples->delete($previousSample);
        }

        if ($data->sampleResume !== null) {
            DeriveTemplateSectionsJob::dispatch($template->id);
        }

        return $template->refresh();
    }
}
