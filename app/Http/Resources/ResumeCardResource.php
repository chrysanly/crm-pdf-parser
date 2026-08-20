<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resume
 */
final class ResumeCardResource extends JsonResource
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'page_count' => $this->page_count,
            'file_size_kb' => (int) ceil($this->file_size / 1024),
            'failure_reason' => $this->failure_reason,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'parsed_at' => $this->parsed_at?->toIso8601String(),
        ];
    }
}
