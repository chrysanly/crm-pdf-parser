<?php

declare(strict_types=1);

namespace Tests\Feature\Resume;

use App\Enums\ResumeStatus;
use App\Jobs\ParseResumeJob;
use App\Models\Company;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ResumeUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pdf_is_stored_privately_and_parsing_is_queued(): void
    {
        Queue::fake();
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(
            route('companies.resumes.store', $company),
            ['file' => $this->pdf()],
        );

        $resume = Resume::query()->firstOrFail();

        $response->assertRedirect(route('resumes.show', $resume));
        $this->assertSame(ResumeStatus::Pending, $resume->status);
        $this->assertSame('candidate-cv.pdf', $resume->original_filename);

        // Random filename on the private disk, never the public one (RULES §5.7).
        $this->assertStringStartsWith("resumes/{$company->public_id}/", $resume->stored_path);
        $this->assertStringNotContainsString('candidate-cv', $resume->stored_path);
        Storage::disk('local')->assertExists($resume->stored_path);

        Queue::assertPushed(ParseResumeJob::class, fn (ParseResumeJob $job): bool => $job->resumeId === $resume->id);
    }

    /**
     * RULES §5.5: re-uploading the same document for the same company is a no-op
     * that returns the existing record — not a duplicate and not a 500.
     */
    public function test_uploading_the_same_file_twice_does_not_create_a_duplicate(): void
    {
        Queue::fake();
        Storage::fake('local');

        $company = Company::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('companies.resumes.store', $company), ['file' => $this->pdf()]);
        $this->actingAs($user)->post(route('companies.resumes.store', $company), ['file' => $this->pdf()])
            ->assertRedirect();

        $this->assertSame(1, Resume::query()->count());
        Queue::assertPushed(ParseResumeJob::class, 1);
    }

    public function test_the_same_file_can_be_filed_against_a_different_company(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        [$first, $second] = Company::factory()->count(2)->create();

        $this->actingAs($user)->post(route('companies.resumes.store', $first), ['file' => $this->pdf()]);
        $this->actingAs($user)->post(route('companies.resumes.store', $second), ['file' => $this->pdf()]);

        $this->assertSame(2, Resume::query()->count());
    }

    public function test_a_non_pdf_upload_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post(route('companies.resumes.store', Company::factory()->create()), [
                'file' => UploadedFile::fake()->create('resume.docx', 40, 'application/msword'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Resume::query()->count());
    }

    public function test_an_inactive_company_refuses_uploads(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('companies.resumes.store', Company::factory()->inactive()->create()), [
                'file' => $this->pdf(),
            ])
            ->assertForbidden();
    }

    public function test_only_the_uploader_can_download_the_original_pdf(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $resume = Resume::factory()->create(['uploaded_by' => $owner->id]);
        Storage::disk('local')->put($resume->stored_path, '%PDF-1.4');

        $this->actingAs(User::factory()->create())
            ->get(route('resumes.file', $resume))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('resumes.file', $resume))
            ->assertOk();
    }

    public function test_guests_cannot_upload(): void
    {
        $this->post(route('companies.resumes.store', Company::factory()->create()), [
            'file' => $this->pdf(),
        ])->assertRedirect(route('login'));
    }

    /**
     * A minimal but genuine PDF: `mimetypes` validation reads the real content type,
     * so a fake()->create() with a spoofed mime would not exercise the rule.
     */
    private function pdf(string $name = 'candidate-cv.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'resume').'.pdf';

        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
