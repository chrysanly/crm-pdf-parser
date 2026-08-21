<?php

declare(strict_types=1);

namespace Tests\Feature\Resume;

use App\Actions\Resume\ParseResume;
use App\Contracts\ResumeParser;
use App\DTOs\Parsing\ParsedResume;
use App\Enums\LogoPlacement;
use App\Enums\LogoSize;
use App\Enums\TemplateLayout;
use App\Models\Company;
use App\Models\Resume;
use App\Models\User;
use App\Services\Ats\AtsResumeFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The preview must render THE UPLOADED FILE's content — never a fixture. These
 * tests bind a parser that echoes back the bytes it was handed, so a regression
 * that ignores the upload (e.g. shipping with SIDECAR_DRIVER=fake in production)
 * fails here.
 */
final class AtsPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_preview_renders_data_taken_from_the_uploaded_file(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create(['name' => 'Nakheel Engineering']);
        $user = User::factory()->create();

        $this->bindEchoParser();

        $this->actingAs($user)->post(route('companies.resumes.store', $company), [
            'file' => $this->pdfContaining('Fatima Al-Suwaidi'),
        ]);

        $resume = Resume::query()->firstOrFail();

        // QUEUE_CONNECTION=sync in phpunit.xml, so the job already ran.
        $this->assertSame('Fatima Al-Suwaidi', $resume->candidate_name);

        $this->actingAs($user)
            ->get(route('resumes.show', $resume))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resumes/show')
                ->where('resume.candidate_name', 'Fatima Al-Suwaidi')
                ->where('resume.ats.header.name', 'Fatima Al-Suwaidi')
                ->where('resume.ats.template', $company->resumeTemplate->layout->value)
            );
    }

    public function test_two_different_uploads_produce_two_different_previews(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create();

        $this->bindEchoParser();

        foreach (['Yousef Haddad', 'Mariam Kanaan'] as $name) {
            $this->actingAs($user)->post(route('companies.resumes.store', $company), [
                'file' => $this->pdfContaining($name),
            ]);
        }

        $this->assertSame(
            ['Yousef Haddad', 'Mariam Kanaan'],
            Resume::query()->orderBy('id')->pluck('candidate_name')->all(),
        );
    }

    public function test_the_assigned_template_decides_the_section_order_of_the_same_upload(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->bindEchoParser();

        $classic = Company::factory()->layout(TemplateLayout::Classic)->create();
        $modern = Company::factory()->layout(TemplateLayout::Modern)->create();

        $orders = [];

        foreach ([$classic, $modern] as $company) {
            $resume = Resume::factory()->for($company)->create(['uploaded_by' => $user->id]);
            Storage::disk('local')->put($resume->stored_path, $this->pdfBytes('Same Person'));

            app(ParseResume::class)->handle($resume);

            $document = $this->atsFor($resume->fresh(), $company->load('resumeTemplate'));
            $orders[] = array_column($document['sections'], 'key');
        }

        $this->assertNotSame($orders[0], $orders[1]);
        $this->assertSame('experience', $orders[0][1]);   // classic: summary, experience…
        $this->assertSame('skills', $orders[1][1]);       // modern: summary, skills…
    }

    public function test_the_professional_layout_reaches_the_page_with_its_letterhead(): void
    {
        $company = Company::factory()
            ->layout(TemplateLayout::Professional)
            ->create([
                'logo_path' => 'company-logos/acme.png',
                'logo_placement' => LogoPlacement::Centre,
                'logo_size' => LogoSize::Large,
            ]);

        $resume = Resume::factory()->parsed()->for($company)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('resumes.show', $resume))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('resume.ats.template', 'professional')
                ->where('resume.ats.header.centred', true)
                ->where('resume.ats.header.logo.placement', 'centre')
                ->where('resume.ats.header.logo.pixels', 72)
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function atsFor(Resume $resume, Company $company): array
    {
        return app(AtsResumeFormatter::class)->format(
            ParsedResume::fromArray((array) $resume->parsed_data),
            $company,
            $resume->resumeTemplate,
        );
    }

    /**
     * A parser that reads the real bytes it was given, so the assertions above can
     * only pass if the pipeline carries the upload all the way through.
     */
    private function bindEchoParser(): void
    {
        $this->swap(ResumeParser::class, new class implements ResumeParser
        {
            public function parse(string $storedPath, string $originalFilename): ParsedResume
            {
                $contents = (string) Storage::disk('local')->get($storedPath);

                preg_match('/NAME:(?<name>[^\n]+)/', $contents, $matches);

                return ParsedResume::fromArray([
                    'contact' => [
                        'full_name' => trim($matches['name'] ?? 'unknown'),
                        'email' => 'candidate@example.com',
                        'phone' => '+971500000000',
                    ],
                    'summary' => 'Summary extracted from '.$originalFilename,
                    'experience' => [[
                        'title' => 'Extracted Role',
                        'company' => 'Extracted Employer',
                        'start_date' => '2020-01',
                        'is_current' => true,
                        'highlights' => ['Delivered 12 projects.'],
                    ]],
                    'education' => [['degree' => 'BSc Engineering', 'institution' => 'UAE University']],
                    'skills' => ['One', 'Two', 'Three', 'Four', 'Five'],
                    'certifications' => ['PMP'],
                    'languages' => ['Arabic', 'English'],
                    'warnings' => [],
                    'page_count' => 1,
                    'parser_version' => 'echo-1.0',
                ]);
            }
        });
    }

    private function pdfBytes(string $name): string
    {
        return "%PDF-1.4\nNAME:{$name}\n%%EOF\n";
    }

    private function pdfContaining(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'resume').'.pdf';
        file_put_contents($path, $this->pdfBytes($name));

        return new UploadedFile($path, 'candidate-cv.pdf', 'application/pdf', null, true);
    }
}
