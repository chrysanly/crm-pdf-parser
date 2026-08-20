<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Slim shape for the companies index (RULES §6.2) — mirrors CompanyCard in
 * resources/js/types/models.ts.
 *
 * @mixin Company
 */
final class CompanyCardResource extends JsonResource
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
            'logo_url' => $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path),
            'brand_color' => $this->brand_color,
            'resume_template' => $this->resume_template->value,
            'resume_template_label' => $this->resume_template->label(),
            'is_active' => $this->is_active,
            'resumes_count' => $this->whenCounted('resumes'),
        ];
    }
}
