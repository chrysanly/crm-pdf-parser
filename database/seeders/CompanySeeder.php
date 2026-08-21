<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LogoPlacement;
use App\Enums\LogoSize;
use App\Models\Company;
use App\Models\Resume;
use App\Models\ResumeTemplate;
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
                'resume_template' => 'professional',
                'logo_placement' => LogoPlacement::Right,
                'logo_size' => LogoSize::Medium,
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
                'resume_template' => 'professional-skills-first',
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
                'resume_template' => 'compact',
                'formatting_notes' => 'Single page. Certifications (Society of Engineers, PMP) '
                    .'must appear before education.',
            ],
        ];

        $templates = ResumeTemplate::query()->pluck('id', 'slug');

        foreach ($companies as $attributes) {
            $templateSlug = (string) $attributes['resume_template'];
            unset($attributes['resume_template']);

            Company::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [
                    ...$attributes,
                    'resume_template_id' => $templates[$templateSlug] ?? $templates->first(),
                    'is_active' => true,
                ],
            );
        }

        // Deliberately NO seeded "parsed" resumes: fabricated parse output is
        // indistinguishable from a real one in the UI and reads as a bug (it is
        // how sample data ended up on a real upload's preview). Companies start
        // empty — upload a PDF to see a genuine parse.
        //
        // One failed row is kept so the error/retry path is visible without an
        // upload; it carries no fabricated candidate data.
        Resume::factory()
            ->failed(__('No readable text was found in this PDF. Scanned images are not supported yet.'))
            ->for(Company::query()->where('slug', 'gulf-freight-partners')->firstOrFail())
            ->create([
                'uploaded_by' => $uploader->id,
                'original_filename' => 'scanned-cv.pdf',
            ]);
    }
}
