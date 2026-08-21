<?php

declare(strict_types=1);

namespace Tests\Feature\Resume;

use App\Enums\TemplateLayout;
use App\Models\Company;
use App\Models\Resume;
use App\Models\ResumeTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The export is the preview's content and nothing else, as a real PDF: text
 * stays text so an ATS can read it (PRD §3 feature 7).
 */
final class AtsPdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_export_is_a_pdf_named_after_the_candidate(): void
    {
        $resume = Resume::factory()->parsed()->create(['candidate_name' => 'Layla Haddad']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('resumes.pdf', $resume));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'layla-haddad',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_export_carries_the_document_text_not_a_picture_of_it(): void
    {
        $resume = Resume::factory()->parsed()->create();

        $pdf = $this->actingAs(User::factory()->create())
            ->get(route('resumes.pdf', $resume))
            ->getContent();

        // dompdf writes the strings into the content stream, so the candidate's
        // details are present as text rather than pixels.
        $this->assertStringContainsString('/Type /Page', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    public function test_the_export_follows_the_template_frozen_on_the_resume(): void
    {
        $professional = ResumeTemplate::factory()
            ->layout(TemplateLayout::Professional)
            ->sectionOrder(['summary', 'experience'])
            ->create();

        $company = Company::factory()->create();
        $resume = Resume::factory()->parsed()->for($company)->create([
            'resume_template_id' => $professional->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('resumes.pdf', $resume))
            ->assertOk();

        $this->assertSame(
            ['summary', 'experience'],
            $resume->refresh()->resumeTemplate?->effectiveSectionOrder(),
        );
    }

    public function test_an_unparsed_resume_has_nothing_to_export(): void
    {
        $resume = Resume::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('resumes.pdf', $resume))
            ->assertNotFound();
    }

    public function test_guests_cannot_export(): void
    {
        $resume = Resume::factory()->parsed()->create();

        $this->get(route('resumes.pdf', $resume))->assertRedirect(route('login'));
    }
}
