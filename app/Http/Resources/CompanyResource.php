<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Company
 */
final class CompanyResource extends JsonResource
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
            'industry' => $this->industry,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'website' => $this->website,
            'logo_url' => $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path),
            'brand_color' => $this->brand_color,
            'resume_template' => $this->resume_template->value,
            'resume_template_label' => $this->resume_template->label(),
            'section_order' => $this->effectiveSectionOrder(),
            'has_custom_section_order' => $this->section_order !== null && $this->section_order !== [],
            'formatting_notes' => $this->formatting_notes,
            'is_active' => $this->is_active,
            'resumes_count' => $this->whenCounted('resumes'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
