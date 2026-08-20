<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ResumeTemplate;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'industry' => fake()->randomElement([
                'Logistics', 'Construction', 'Healthcare', 'Financial Services',
                'Hospitality', 'Technology', 'Retail',
            ]),
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => '+9715'.fake()->numerify('########'),
            'website' => 'https://'.fake()->domainName(),
            'logo_path' => null,
            'brand_color' => fake()->randomElement(['#1F2937', '#0F766E', '#7C2D12', '#1D4ED8', '#4C1D95']),
            'resume_template' => fake()->randomElement(ResumeTemplate::cases()),
            'section_order' => null,
            'formatting_notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function template(ResumeTemplate $template): self
    {
        return $this->state(fn (): array => ['resume_template' => $template]);
    }
}
