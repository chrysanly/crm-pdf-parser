<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ResumeTemplate;
use App\Models\Company;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic demo data, no lorem ipsum (RULES §9.3). Each company shows a
 * different house style so the ATS preview is comparable across templates.
 */
final class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $uploader = User::query()->first() ?? User::factory()->create();

        /** @var list<array<string, mixed>> $companies */
        $companies = [
            [
                'name' => 'Gulf Freight Partners',
                'slug' => 'gulf-freight-partners',
                'industry' => 'Logistics',
                'contact_email' => 'talent@gulffreight.ae',
                'contact_phone' => '+97143214567',
                'website' => 'https://gulffreight.example.ae',
                'brand_color' => '#0F766E',
                'resume_template' => ResumeTemplate::Classic,
                'section_order' => null,
                'formatting_notes' => 'Reverse-chronological only. Dates as "Mon YYYY". '
                    .'Keep every role to a maximum of four achievement bullets.',
            ],
            [
                'name' => 'Almasa Retail Group',
                'slug' => 'almasa-retail-group',
                'industry' => 'Retail',
                'contact_email' => 'careers@almasaretail.ae',
                'contact_phone' => '+97165551234',
                'website' => 'https://almasaretail.example.ae',
                'brand_color' => '#7C2D12',
                'resume_template' => ResumeTemplate::Modern,
                'section_order' => ['summary', 'skills', 'experience', 'education', 'languages'],
                'formatting_notes' => 'Skills band directly under the summary. '
                    .'Certifications are optional; languages are mandatory for store-facing roles.',
            ],
            [
                'name' => 'Nakheel Engineering Consultants',
                'slug' => 'nakheel-engineering-consultants',
                'industry' => 'Construction',
                'contact_email' => 'hr@nakheeleng.ae',
                'contact_phone' => '+97124447788',
                'website' => 'https://nakheeleng.example.ae',
                'brand_color' => '#1D4ED8',
                'resume_template' => ResumeTemplate::Compact,
                'section_order' => null,
                'formatting_notes' => 'Single page. Certifications (Society of Engineers, PMP) '
                    .'must appear before education.',
            ],
        ];

        foreach ($companies as $attributes) {
            $company = Company::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [...$attributes, 'is_active' => true],
            );

            if ($company->resumes()->exists()) {
                continue;
            }

            Resume::factory()
                ->parsed()
                ->for($company)
                ->create([
                    'uploaded_by' => $uploader->id,
                    'original_filename' => 'layla-haddad-cv.pdf',
                ]);
        }

        // One of each non-happy state so the UI's error/empty paths are visible.
        Resume::factory()
            ->failed()
            ->for(Company::query()->where('slug', 'gulf-freight-partners')->firstOrFail())
            ->create([
                'uploaded_by' => $uploader->id,
                'original_filename' => 'scanned-cv.pdf',
            ]);
    }
}
