<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

/**
 * A labelled skills line, e.g. "Databases: MySQL, PostgreSQL". `label` is null
 * for resumes that list skills without categories.
 */
final readonly class SkillGroup
{
    /**
     * @param  list<string>  $items
     */
    public function __construct(
        public ?string $label,
        public array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            label: Cast::string($payload['label'] ?? null),
            items: Cast::stringList($payload['items'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'items' => $this->items,
        ];
    }
}
