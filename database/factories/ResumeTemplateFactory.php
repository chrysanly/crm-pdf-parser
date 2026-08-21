<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TemplateLayout;
use App\Models\ResumeTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ResumeTemplate>
 */
final class ResumeTemplateFactory extends Factory
{
    protected $model = ResumeTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $layout = fake()->randomElement(TemplateLayout::cases());
        $name = Str::headline($layout->value).' '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => $layout->label(),
            'layout' => $layout,
            'section_order' => null,
            'is_active' => true,
        ];
    }

    public function layout(TemplateLayout $layout): self
    {
        return $this->state(fn (): array => ['layout' => $layout]);
    }

    /**
     * @param  list<string>  $sections
     */
    public function sectionOrder(array $sections): self
    {
        return $this->state(fn (): array => ['section_order' => $sections]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
