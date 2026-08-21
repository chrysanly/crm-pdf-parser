<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

/**
 * One row of a "Personal Details" block, e.g. "Date of Birth: June 24, 1997".
 */
final readonly class DetailItem
{
    public function __construct(
        public string $label,
        public string $value,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            label: Cast::string($payload['label'] ?? null) ?? '',
            value: Cast::string($payload['value'] ?? null) ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
        ];
    }
}
