<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class ExperienceEntry
{
    /**
     * @param  list<string>  $highlights
     */
    public function __construct(
        public string $title,
        public ?string $company,
        public ?string $location,
        public ?string $startDate,
        public ?string $endDate,
        public bool $isCurrent,
        public array $highlights,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            title: Cast::string($payload['title'] ?? null) ?? '',
            company: Cast::string($payload['company'] ?? null),
            location: Cast::string($payload['location'] ?? null),
            startDate: Cast::string($payload['start_date'] ?? null),
            endDate: Cast::string($payload['end_date'] ?? null),
            isCurrent: (bool) ($payload['is_current'] ?? false),
            highlights: Cast::stringList($payload['highlights'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'company' => $this->company,
            'location' => $this->location,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'is_current' => $this->isCurrent,
            'highlights' => $this->highlights,
        ];
    }
}
