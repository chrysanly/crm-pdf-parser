<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ResumeStatus;
use App\Models\Company;
use App\Models\Resume;
use App\Models\User;
use App\Services\Parsing\FakeResumeParser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Resume>
 */
final class ResumeFactory extends Factory
{
    protected $model = Resume::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'uploaded_by' => User::factory(),
            'original_filename' => Str::slug(fake()->name()).'-cv.pdf',
            'stored_path' => 'resumes/'.Str::ulid()->toString().'/'.Str::random(40).'.pdf',
            'file_hash' => hash('sha256', Str::random(32)),
            'file_size' => fake()->numberBetween(80_000, 900_000),
            'page_count' => null,
            'status' => ResumeStatus::Pending,
            'candidate_name' => null,
            'candidate_email' => null,
            'parsed_data' => null,
            'failure_reason' => null,
            'parsed_at' => null,
        ];
    }

    public function parsed(): self
    {
        $payload = FakeResumeParser::payload();

        return $this->state(fn (): array => [
            'status' => ResumeStatus::Parsed,
            'candidate_name' => $payload['contact']['full_name'],
            'candidate_email' => $payload['contact']['email'],
            'page_count' => $payload['page_count'],
            'parsed_data' => $payload,
            'parsed_at' => now(),
        ]);
    }

    public function failed(string $reason = 'No readable text was found in this PDF.'): self
    {
        return $this->state(fn (): array => [
            'status' => ResumeStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }
}
