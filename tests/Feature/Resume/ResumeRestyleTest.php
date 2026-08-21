<?php

declare(strict_types=1);

namespace Tests\Feature\Resume;

use App\Enums\ResumeStatus;
use App\Enums\TemplateLayout;
use App\Jobs\ParseResumeJob;
use App\Models\Company;
use App\Models\Resume;
use App\Models\ResumeTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Re-styling and re-parsing an existing document — the two things the preview
 * page offers once a resume has been produced (PRD BR-6d).
 */
final class ResumeRestyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_offers_the_active_templates(): void
    {
        ResumeTemplate::factory()->create(['name' => 'Live style']);
        ResumeTemplate::factory()->inactive()->create(['name' => 'Retired style']);

        $resume = Resume::factory()->parsed()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('resumes.show', $resume))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resumes/show')
                ->has('templates')
                ->where('resume.resume_template_slug', $resume->resumeTemplate?->slug)
            );
    }

    public function test_applying_another_template_restyles_the_document_without_reparsing(): void
    {
        Queue::fake();

        $original = ResumeTemplate::factory()->layout(TemplateLayout::Classic)->create();
        $replacement = ResumeTemplate::factory()
            ->layout(TemplateLayout::Modern)
            ->sectionOrder(['skills', 'summary'])
            ->create();

        $company = Company::factory()->template($original)->create();
        $resume = Resume::factory()->parsed()->for($company)->create([
            'resume_template_id' => $original->id,
        ]);

        $before = $resume->parsed_data;

        $this->actingAs(User::factory()->create())
            ->from(route('resumes.show', $resume))
            ->put(route('resumes.template', $resume), [
                'resume_template' => $replacement->slug,
            ])
            ->assertRedirect(route('resumes.show', $resume));

        $resume->refresh();

        $this->assertSame($replacement->id, $resume->resume_template_id);
        // Presentation only: the parsed document is untouched and nothing is queued.
        $this->assertSame($before, $resume->parsed_data);
        $this->assertSame(ResumeStatus::Parsed, $resume->status);
        Queue::assertNothingPushed();
    }

    public function test_an_unknown_or_archived_template_is_rejected(): void
    {
        $archived = ResumeTemplate::factory()->create();
        $archived->delete();

        $resume = Resume::factory()->parsed()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('resumes.template', $resume), ['resume_template' => 'nope'])
            ->assertSessionHasErrors('resume_template');

        $this->actingAs($user)
            ->put(route('resumes.template', $resume), ['resume_template' => $archived->slug])
            ->assertSessionHasErrors('resume_template');
    }

    public function test_a_document_mid_parse_cannot_be_restyled(): void
    {
        $template = ResumeTemplate::factory()->create();
        $resume = Resume::factory()->create(['status' => ResumeStatus::Processing]);

        $this->actingAs(User::factory()->create())
            ->put(route('resumes.template', $resume), ['resume_template' => $template->slug])
            ->assertForbidden();
    }

    /**
     * ParseResumeJob skips resumes that are already parsed, so a re-parse has to
     * reset the status — otherwise the button silently does nothing.
     */
    public function test_reparsing_an_already_parsed_resume_queues_it_again(): void
    {
        Queue::fake();

        $resume = Resume::factory()->parsed()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('resumes.show', $resume))
            ->post(route('resumes.reparse', $resume))
            ->assertRedirect(route('resumes.show', $resume));

        $this->assertSame(ResumeStatus::Pending, $resume->refresh()->status);
        Queue::assertPushed(
            ParseResumeJob::class,
            fn (ParseResumeJob $job): bool => $job->resumeId === $resume->id,
        );
    }

    public function test_a_failed_resume_loses_its_failure_reason_when_requeued(): void
    {
        Queue::fake();

        $resume = Resume::factory()->failed('The sidecar was unreachable.')->create();

        $this->actingAs(User::factory()->create())
            ->from(route('resumes.show', $resume))
            ->post(route('resumes.reparse', $resume));

        $resume->refresh();

        $this->assertSame(ResumeStatus::Pending, $resume->status);
        $this->assertNull($resume->failure_reason);
    }
}
