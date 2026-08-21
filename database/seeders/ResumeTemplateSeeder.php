<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TemplateLayout;
use App\Models\ResumeTemplate;
use Illuminate\Database\Seeder;

/**
 * The house styles a fresh install starts with: one per built-in layout, plus a
 * skills-first variant so the section-order override is visible in the UI
 * (RULES §9.3).
 */
final class ResumeTemplateSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array<string, mixed>> $templates */
        $templates = [
            [
                'name' => 'Classic',
                'slug' => 'classic',
                'layout' => TemplateLayout::Classic,
                'section_order' => null,
                'description' => 'Chronological, single column. The safe default for any ATS.',
            ],
            [
                'name' => 'Modern',
                'slug' => 'modern',
                'layout' => TemplateLayout::Modern,
                'section_order' => null,
                'description' => 'Summary-led with a skills band under it.',
            ],
            [
                'name' => 'Compact',
                'slug' => 'compact',
                'layout' => TemplateLayout::Compact,
                'section_order' => null,
                'description' => 'One page, dense. Drops certifications and languages.',
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'layout' => TemplateLayout::Professional,
                'section_order' => null,
                'description' => 'Centred header with ruled sections, personal details first.',
            ],
            [
                'name' => 'Professional — skills first',
                'slug' => 'professional-skills-first',
                'layout' => TemplateLayout::Professional,
                'section_order' => ['summary', 'skills', 'experience', 'certifications', 'education', 'languages'],
                'description' => 'Professional layout for skills-led hiring: no personal details block.',
            ],
        ];

        foreach ($templates as $attributes) {
            ResumeTemplate::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [...$attributes, 'is_active' => true],
            );
        }
    }
}
