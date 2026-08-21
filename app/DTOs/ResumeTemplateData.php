<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\TemplateLayout;
use App\Http\Requests\ResumeTemplate\StoreResumeTemplateRequest;
use App\Http\Requests\ResumeTemplate\UpdateResumeTemplateRequest;
use Illuminate\Http\UploadedFile;

final readonly class ResumeTemplateData
{
    /**
     * @param  list<string>|null  $sectionOrder  null = follow the layout's default order
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public TemplateLayout $layout,
        public ?array $sectionOrder,
        public bool $isActive,
        /** A sample resume to read the printed section order from. */
        public ?UploadedFile $sampleResume = null,
        public bool $removeSample = false,
    ) {}

    public static function fromRequest(StoreResumeTemplateRequest|UpdateResumeTemplateRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var list<string>|null $sectionOrder */
        $sectionOrder = isset($validated['section_order']) && is_array($validated['section_order']) && $validated['section_order'] !== []
            ? array_values(array_unique(array_map('strval', $validated['section_order'])))
            : null;

        $description = isset($validated['description']) ? trim((string) $validated['description']) : '';
        $sample = $request->file('sample_resume');

        return new self(
            name: trim((string) $validated['name']),
            description: $description === '' ? null : $description,
            layout: TemplateLayout::from((string) $validated['layout']),
            sectionOrder: $sectionOrder,
            isActive: (bool) ($validated['is_active'] ?? true),
            sampleResume: $sample instanceof UploadedFile ? $sample : null,
            removeSample: (bool) ($validated['remove_sample'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'layout' => $this->layout,
            'section_order' => $this->sectionOrder,
            'is_active' => $this->isActive,
        ];
    }
}
