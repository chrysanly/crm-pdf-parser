<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ResumeTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim shape for the templates index (RULES §6.2) — mirrors ResumeTemplateCard
 * in resources/js/types/models.ts.
 *
 * @mixin ResumeTemplate
 */
final class ResumeTemplateCardResource extends JsonResource
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
            'is_active' => $this->is_active,
            'companies_count' => $this->whenCounted('companies'),
            'resumes_count' => $this->whenCounted('resumes'),
        ];
    }
}
