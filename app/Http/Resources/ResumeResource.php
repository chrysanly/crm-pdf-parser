<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Parsing\ParsedResume;
use App\Models\Resume;
use App\Services\Ats\AtsResumeFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full resume, including the ATS document already laid out for the company's
 * house style — React renders it, it never computes it (DESIGN §1.4).
 *
 * @mixin Resume
 */
final class ResumeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'original_filename' => $this->original_filename,
            'candidate_name' => $this->candidate_name,
            'candidate_email' => $this->candidate_email,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'page_count' => $this->page_count,
            'file_size_kb' => (int) ceil($this->file_size / 1024),
            'failure_reason' => $this->failure_reason,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'parsed_at' => $this->parsed_at?->toIso8601String(),
            'company' => new CompanyCardResource($this->whenLoaded('company')),
            // The style frozen at upload: name to show, slug to switch with.
            'resume_template' => $this->resumeTemplate?->name,
            'resume_template_slug' => $this->resumeTemplate?->slug,
            'ats' => $this->atsDocument(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function atsDocument(): ?array
    {
        $parsed = $this->parsed_data;

        if ($parsed === null || ! $this->relationLoaded('company')) {
            return null;
        }

        return app(AtsResumeFormatter::class)->format(
            ParsedResume::fromArray($parsed),
            $this->company,
            $this->resumeTemplate,
        );
    }
}
