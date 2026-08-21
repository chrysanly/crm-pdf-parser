<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ResumeStatus;
use App\Models\Company;
use App\Models\Resume;
use App\Models\ResumeTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The dashboard answers three questions: what came in, what is stuck, and what
 * needs me. Each assertion below pins one of them.
 */
final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_it_counts_companies_resumes_and_templates(): void
    {
        $company = Company::factory()->create();
        Company::factory()->inactive()->create();

        Resume::factory()->parsed()->count(2)->for($company)->create();
        Resume::factory()->failed('The sidecar was unreachable.')->for($company)->create();
        Resume::factory()->for($company)->create(['status' => ResumeStatus::Pending]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('summary.totals.resumes', 4)
                ->where('summary.totals.parsed', 2)
                ->where('summary.totals.failed', 1)
                ->where('summary.totals.in_flight', 1)
                ->where('summary.totals.companies', 2)
                ->where('summary.totals.companies_active', 1)
                // Each factory-made company brings its own template.
                ->has('summary.totals.templates')
            );
    }

    public function test_failed_resumes_lead_the_needs_attention_list(): void
    {
        $company = Company::factory()->create();

        Resume::factory()->for($company)->create([
            'status' => ResumeStatus::Pending,
            'original_filename' => 'queued.pdf',
        ]);

        Resume::factory()
            ->failed('No readable text was found in this PDF.')
            ->for($company)
            ->create(['original_filename' => 'broken.pdf']);

        // Parsed documents need nothing, so they stay off the list.
        Resume::factory()->parsed()->for($company)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('attention', 2)
                ->where('attention.0.original_filename', 'broken.pdf')
                ->where('attention.0.failure_reason', 'No readable text was found in this PDF.')
                // The list is cross-company, so each row carries its client.
                ->where('attention.0.company.name', $company->name)
            );
    }

    public function test_the_intake_chart_covers_a_fortnight_including_empty_days(): void
    {
        Resume::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('summary.trend', 14)
                ->where('summary.trend.13.count', 1)   // today
                ->where('summary.trend.0.count', 0)    // thirteen days ago
            );
    }

    public function test_the_ats_average_is_taken_over_parsed_documents_only(): void
    {
        Resume::factory()->parsed()->count(2)->create();
        Resume::factory()->failed('unreadable')->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.ats.sampled', 2)
                ->whereNot('summary.ats.average', null)
                ->whereNot('summary.ats.band', null)
            );
    }

    public function test_it_reports_no_score_before_anything_is_parsed(): void
    {
        Resume::factory()->create(['status' => ResumeStatus::Pending]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.ats.average', null)
                ->where('summary.ats.sampled', 0)
            );
    }

    public function test_it_ranks_the_busiest_companies_and_template_usage(): void
    {
        $template = ResumeTemplate::factory()->create(['name' => 'Busy style']);

        $busy = Company::factory()->template($template)->create(['name' => 'Busy Client']);
        $quiet = Company::factory()->template($template)->create(['name' => 'Quiet Client']);

        Resume::factory()->count(3)->for($busy)->create(['resume_template_id' => $template->id]);
        Resume::factory()->for($quiet)->create(['resume_template_id' => $template->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.top_companies.0.name', 'Busy Client')
                ->where('summary.top_companies.0.resumes', 3)
                ->where('summary.template_usage.0.name', 'Busy style')
                ->where('summary.template_usage.0.companies', 2)
                ->where('summary.template_usage.0.resumes', 4)
            );
    }

    public function test_a_fresh_install_has_an_empty_dashboard_rather_than_zeros_everywhere(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.totals.resumes', 0)
                ->where('summary.totals.companies', 0)
                ->has('attention', 0)
                ->has('recent', 0)
            );
    }

    public function test_the_lists_do_not_lazy_load_the_company(): void
    {
        // Model::preventLazyLoading() is on outside production, so an unloaded
        // relation would throw here rather than quietly N+1 (CLAUDE.md §6.4).
        Resume::factory()->parsed()->count(3)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }
}
