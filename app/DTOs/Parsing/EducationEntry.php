<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class EducationEntry
{
    public function __construct(
        public string $degree,
        public ?string $institution,
        public ?string $location,
        public ?string $startDate,
        public ?string $endDate,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            degree: Cast::string($payload['degree'] ?? null) ?? '',
            institution: Cast::string($payload['institution'] ?? null),
            location: Cast::string($payload['location'] ?? null),
            startDate: Cast::string($payload['start_date'] ?? null),
            endDate: Cast::string($payload['end_date'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'degree' => $this->degree,
            'institution' => $this->institution,
            'location' => $this->location,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
