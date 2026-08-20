<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ResumeTemplate;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use Illuminate\Http\UploadedFile;

final readonly class CompanyData
{
    /**
     * @param  list<string>|null  $sectionOrder
     */
    public function __construct(
        public string $name,
        public ?string $industry,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $website,
        public string $brandColor,
        public ResumeTemplate $resumeTemplate,
        public ?array $sectionOrder,
        public ?string $formattingNotes,
        public bool $isActive,
        public ?UploadedFile $logo = null,
        public bool $removeLogo = false,
    ) {}

    public static function fromRequest(StoreCompanyRequest|UpdateCompanyRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $logo = $request->file('logo');

        /** @var list<string>|null $sectionOrder */
        $sectionOrder = isset($validated['section_order']) && is_array($validated['section_order'])
            ? array_values(array_map('strval', $validated['section_order']))
            : null;

        return new self(
            name: (string) $validated['name'],
            industry: self::nullableString($validated['industry'] ?? null),
            contactEmail: self::nullableString($validated['contact_email'] ?? null),
            contactPhone: self::nullableString($validated['contact_phone'] ?? null),
            website: self::nullableString($validated['website'] ?? null),
            brandColor: (string) ($validated['brand_color'] ?? '#1F2937'),
            resumeTemplate: ResumeTemplate::from((string) $validated['resume_template']),
            sectionOrder: $sectionOrder,
            formattingNotes: self::nullableString($validated['formatting_notes'] ?? null),
            isActive: (bool) ($validated['is_active'] ?? true),
            logo: $logo instanceof UploadedFile ? $logo : null,
            removeLogo: (bool) ($validated['remove_logo'] ?? false),
        );
    }

    /**
     * Attributes ready for a mass-assign. The logo path is set by the Action after
     * the file lands on disk, so it is deliberately absent here.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'industry' => $this->industry,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'website' => $this->website,
            'brand_color' => $this->brandColor,
            'resume_template' => $this->resumeTemplate,
            'section_order' => $this->sectionOrder,
            'formatting_notes' => $this->formattingNotes,
            'is_active' => $this->isActive,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
