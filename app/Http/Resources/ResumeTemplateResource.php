<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ResumeTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResumeTemplate
 */
final class ResumeTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'layout' => $this->layout->value,
            'layout_label' => $this->layout->label(),
            'section_order' => $this->effectiveSectionOrder(),
            'has_custom_section_order' => $this->section_order !== null && $this->section_order !== [],
            // The sample resume the section order was derived from. The file
            // itself is never exposed — it is a real candidate CV (PII).
            'sample_filename' => $this->sample_filename,
            'sample_status' => $this->sample_status?->value,
            'sample_status_label' => $this->sample_status?->label(),
            'sample_failure_reason' => $this->sample_failure_reason,
            'is_active' => $this->is_active,
            'companies_count' => $this->whenCounted('companies'),
            'resumes_count' => $this->whenCounted('resumes'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
