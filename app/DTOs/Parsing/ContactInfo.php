<?php

declare(strict_types=1);

namespace App\DTOs\Parsing;

final readonly class ContactInfo
{
    public function __construct(
        public ?string $fullName,
        public ?string $email,
        public ?string $phone,
        public ?string $location,
        public ?string $linkedin,
        public ?string $website,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            fullName: Cast::string($payload['full_name'] ?? null),
            email: Cast::string($payload['email'] ?? null),
            phone: Cast::string($payload['phone'] ?? null),
            location: Cast::string($payload['location'] ?? null),
            linkedin: Cast::string($payload['linkedin'] ?? null),
            website: Cast::string($payload['website'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'linkedin' => $this->linkedin,
            'website' => $this->website,
        ];
    }
}
