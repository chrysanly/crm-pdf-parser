<?php

declare(strict_types=1);

namespace Tests\Feature\ResumeTemplate;

use App\Actions\ResumeTemplate\DeriveTemplateSections;
use App\Contracts\ResumeParser;
use App\DTOs\Parsing\ParsedResume;
use App\Enums\ResumeStatus;
use App\Enums\TemplateLayout;
use App\Exceptions\ResumeParsingFailedException;
use App\Jobs\DeriveTemplateSectionsJob;
use App\Models\ResumeTemplate;
use App\Models\User;
use App\Services\Parsing\FakeResumeParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Building a template from a sample resume: the sections that document printed,
 * in its order, become the template's order (PRD BR-6c).
 */
final class TemplateFromSampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sample_is_stored_privately_and_queued_for_reading(): void
    {
        Queue::fake();
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post(route('resume-templates.store'), [
                ...$this->payload(),
                'sample_resume' => $this->pdf(),
            ])
            ->assertRedirect(route('resume-templates.index'));

        $template = ResumeTemplate::query()->where('slug', 'house-style')->firstOrFail();

        $this->assertSame(ResumeStatus::Pending, $template->sample_status);
        $this->assertSame('reference-cv.pdf', $template->sample_filename);

        // Random filename on the private disk, never the public one (RULES §5.7).
        $this->assertNotNull($template->sample_path);
        $this->assertStringStartsWith("template-samples/{$template->public_id}/", $template->sample_path);
        $this->assertStringNotContainsString('reference-cv', $template->sample_path);
        Storage::disk('local')->assertExists($template->sample_path);

        Queue::assertPushed(
            DeriveTemplateSectionsJob::class,
            fn (DeriveTemplateSectionsJob $job): bool => $job->templateId === $template->id,
        );
    }

    public function test_the_printed_order_of_the_sample_becomes_the_section_order(): void
    {
        Storage::fake('local');

        $this->bindParserReporting(['details', 'summary', 'skills', 'experience']);

        $template = ResumeTemplate::factory()->layout(TemplateLayout::Classic)->create([
            'section_order' => null,
            'sample_path' => 'template-samples/sample.pdf',
            'sample_filename' => 'reference-cv.pdf',
            'sample_status' => ResumeStatus::Pending,
        ]);

        app(DeriveTemplateSections::class)->handle($template);

        $template->refresh();

        $this->assertSame(ResumeStatus::Parsed, $template->sample_status);
        $this->assertSame(['details', 'summary', 'skills', 'experience'], $template->section_order);
        $this->assertSame($template->section_order, $template->effectiveSectionOrder());
    }

    public function test_sections_the_formatter_cannot_render_are_dropped(): void
    {
        Storage::fake('local');

        $this->bindParserReporting(['summary', 'salary_expectations', 'skills', 'summary']);

        $template = ResumeTemplate::factory()->create([
            'section_order' => null,
            'sample_path' => 'template-samples/sample.pdf',
            'sample_status' => ResumeStatus::Pending,
        ]);

        app(DeriveTemplateSections::class)->handle($template);

        $this->assertSame(['summary', 'skills'], $template->refresh()->section_order);
    }

    public function test_a_sample_with_no_recognisable_sections_keeps_the_layout_default(): void
    {
        Storage::fake('local');

        $this->bindParserReporting([]);

        $template = ResumeTemplate::factory()->layout(TemplateLayout::Modern)->create([
            'section_order' => null,
            'sample_path' => 'template-samples/sample.pdf',
            'sample_status' => ResumeStatus::Pending,
        ]);

        app(DeriveTemplateSections::class)->handle($template);

        $template->refresh();

        $this->assertSame(ResumeStatus::Failed, $template->sample_status);
        $this->assertNotNull($template->sample_failure_reason);
        $this->assertNull($template->section_order);
        $this->assertSame(
            TemplateLayout::Modern->defaultSectionOrder(),
            $template->effectiveSectionOrder(),
        );
    }

    public function test_an_unreadable_sample_is_reported_on_the_template(): void
    {
        Storage::fake('local');

        $this->swap(ResumeParser::class, new class implements ResumeParser
        {
            public function parse(string $storedPath, string $originalFilename): ParsedResume
            {
                throw new ResumeParsingFailedException('no readable text was found');
            }
        });

        $template = ResumeTemplate::factory()->create([
            'sample_path' => 'template-samples/scan.pdf',
            'sample_status' => ResumeStatus::Pending,
        ]);

        try {
            app(DeriveTemplateSections::class)->handle($template);
            $this->fail('The parsing failure should have surfaced.');
        } catch (ResumeParsingFailedException) {
            // expected — the job marks the template and rethrows for the retry.
        }

        $template->refresh();

        $this->assertSame(ResumeStatus::Failed, $template->sample_status);
        $this->assertStringContainsString('no readable text', (string) $template->sample_failure_reason);
    }

    public function test_a_non_pdf_sample_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('resume-templates.store'), [
                ...$this->payload(),
                'sample_resume' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('sample_resume');
    }

    public function test_removing_the_sample_clears_its_state(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('template-samples/old.pdf', '%PDF-1.7');

        $template = ResumeTemplate::factory()->create([
            'sample_path' => 'template-samples/old.pdf',
            'sample_filename' => 'reference-cv.pdf',
            'sample_status' => ResumeStatus::Parsed,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('resume-templates.update', $template), [
                ...$this->payload(),
                'remove_sample' => true,
            ])
            ->assertRedirect(route('resume-templates.index'));

        $template->refresh();

        $this->assertNull($template->sample_path);
        $this->assertNull($template->sample_filename);
        $this->assertNull($template->sample_status);
        Storage::disk('local')->assertMissing('template-samples/old.pdf');
    }

    /**
     * A parser that reports the given printed order, so the derivation can be
     * asserted without a real PDF.
     *
     * @param  list<string>  $order
     */
    private function bindParserReporting(array $order): void
    {
        $payload = [...FakeResumeParser::payload(), 'section_order' => $order];

        $this->swap(ResumeParser::class, new class($payload) implements ResumeParser
        {
            /**
             * @param  array<string, mixed>  $payload
             */
            public function __construct(private readonly array $payload) {}

            public function parse(string $storedPath, string $originalFilename): ParsedResume
            {
                return ParsedResume::fromArray($this->payload);
            }
        });
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'reference-cv.pdf',
            "%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'House style',
            'description' => null,
            'layout' => TemplateLayout::Professional->value,
            'is_active' => true,
        ];
    }
}
