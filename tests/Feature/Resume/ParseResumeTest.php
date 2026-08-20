<?php

declare(strict_types=1);

namespace Tests\Feature\Resume;

use App\Actions\Resume\ParseResume;
use App\Contracts\ResumeParser;
use App\DTOs\Parsing\ParsedResume;
use App\Enums\ResumeStatus;
use App\Exceptions\ResumeParsingFailedException;
use App\Jobs\ParseResumeJob;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ParseResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_parse_stores_the_normalised_document(): void
    {
        $resume = Resume::factory()->create();

        $parsed = app(ParseResume::class)->handle($resume);

        $this->assertSame(ResumeStatus::Parsed, $parsed->status);
        $this->assertSame('Layla Haddad (sample)', $parsed->candidate_name);
        $this->assertSame('layla.haddad@example.com', $parsed->candidate_email);
        $this->assertSame(2, $parsed->page_count);
        $this->assertNotNull($parsed->parsed_at);
        $this->assertNull($parsed->failure_reason);

        $data = $parsed->parsed_data;
        $this->assertIsArray($data);
        $this->assertCount(2, $data['experience']);
        $this->assertSame('Regional Operations Manager', $data['experience'][0]['title']);
    }

    public function test_a_parser_failure_records_a_user_safe_reason(): void
    {
        $resume = Resume::factory()->create();

        $this->swap(ResumeParser::class, new class implements ResumeParser
        {
            public function parse(string $storedPath, string $originalFilename): ParsedResume
            {
                throw ResumeParsingFailedException::unreadable();
            }
        });

        try {
            app(ParseResume::class)->handle($resume);
            $this->fail('Expected ResumeParsingFailedException.');
        } catch (ResumeParsingFailedException $exception) {
            $this->assertStringContainsString('No readable text', $exception->getMessage());
        }

        $resume->refresh();

        $this->assertSame(ResumeStatus::Failed, $resume->status);
        $this->assertStringContainsString('No readable text', (string) $resume->failure_reason);
        $this->assertNull($resume->parsed_data);
    }

    public function test_the_job_skips_a_resume_that_is_already_parsed(): void
    {
        $resume = Resume::factory()->parsed()->create();
        $parsedAt = $resume->parsed_at;

        (new ParseResumeJob($resume->id))->handle(app(ParseResume::class));

        $this->assertEquals($parsedAt, $resume->refresh()->parsed_at);
    }

    public function test_a_failed_job_marks_the_resume_as_failed(): void
    {
        $resume = Resume::factory()->create();

        (new ParseResumeJob($resume->id))->failed(new \RuntimeException('sidecar timed out'));

        $this->assertSame(ResumeStatus::Failed, $resume->refresh()->status);
    }

    public function test_the_ats_document_is_exposed_on_the_resume_page(): void
    {
        $resume = Resume::factory()->parsed()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('resumes.show', $resume))
            ->assertOk()
            ->assertSee('Layla Haddad');
    }
}
