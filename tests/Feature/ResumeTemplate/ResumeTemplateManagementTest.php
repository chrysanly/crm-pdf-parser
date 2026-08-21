<?php

declare(strict_types=1);

namespace Tests\Feature\ResumeTemplate;

use App\Enums\TemplateLayout;
use App\Models\Company;
use App\Models\Resume;
use App\Models\ResumeTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The template CRUD, plus the two rules that make it safe: a template in use
 * cannot be archived, and a resume keeps the template it was uploaded with.
 *
 * The migration seeds one template per built-in layout, so these tests never
 * assume an empty table — they look their own rows up by slug.
 */
final class ResumeTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_the_templates(): void
    {
        $this->get(route('resume-templates.index'))->assertRedirect(route('login'));
    }

    public function test_the_index_lists_templates_with_their_usage(): void
    {
        $template = ResumeTemplate::factory()->create(['name' => 'Al Mutakamela house style']);
        Company::factory()->template($template)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('resume-templates.index'))
            ->assertOk()
            ->assertSee('Al Mutakamela house style');
    }

    public function test_the_create_and_edit_forms_render_with_the_layout_options(): void
    {
        $template = ResumeTemplate::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('resume-templates.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume-templates/create')
                ->has('layouts', count(TemplateLayout::cases()))
            );

        $this->actingAs($user)
            ->get(route('resume-templates.edit', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume-templates/edit')
                ->where('template.slug', $template->slug)
                ->has('template.section_order')
            );
    }

    public function test_the_company_form_offers_only_active_templates(): void
    {
        ResumeTemplate::factory()->create(['name' => 'Live style']);
        ResumeTemplate::factory()->inactive()->create(['name' => 'Retired style']);

        $this->actingAs(User::factory()->create())
            ->get(route('companies.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/create')
                ->where(
                    'templates',
                    fn (Collection $templates): bool => $templates->pluck('name')->contains('Live style')
                        && $templates->pluck('name')->doesntContain('Retired style')
                )
            );
    }

    public function test_a_template_is_created_with_a_slug(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('resume-templates.store'), $this->validPayload());

        $response->assertRedirect(route('resume-templates.index'));

        $template = ResumeTemplate::query()->where('slug', 'al-mutakamela-house-style')->firstOrFail();

        $this->assertSame('Al Mutakamela house style', $template->name);
        $this->assertSame(TemplateLayout::Professional, $template->layout);
        $this->assertSame(['summary', 'skills', 'experience'], $template->section_order);
        $this->assertTrue($template->is_active);
    }

    public function test_duplicate_names_get_distinct_slugs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('resume-templates.store'), $this->validPayload());
        $this->actingAs($user)->post(route('resume-templates.store'), $this->validPayload());

        $this->assertSame(
            ['al-mutakamela-house-style', 'al-mutakamela-house-style-2'],
            ResumeTemplate::query()
                ->where('name', 'Al Mutakamela house style')
                ->orderBy('id')
                ->pluck('slug')
                ->all(),
        );
    }

    public function test_an_empty_section_order_falls_back_to_the_layout_default(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('resume-templates.store'), [
                ...$this->validPayload(),
                'section_order' => [],
            ]);

        $template = ResumeTemplate::query()->where('slug', 'al-mutakamela-house-style')->firstOrFail();

        $this->assertNull($template->section_order);
        $this->assertSame(
            TemplateLayout::Professional->defaultSectionOrder(),
            $template->effectiveSectionOrder(),
        );
    }

    public function test_an_unknown_section_key_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('resume-templates.store'), [
                ...$this->validPayload(),
                'section_order' => ['summary', 'salary_expectations'],
            ])
            ->assertSessionHasErrors('section_order.1');
    }

    public function test_a_repeated_section_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('resume-templates.store'), [
                ...$this->validPayload(),
                'section_order' => ['summary', 'summary'],
            ])
            ->assertSessionHasErrors('section_order.1');
    }

    public function test_an_unknown_layout_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('resume-templates.store'), [
                ...$this->validPayload(),
                'layout' => 'brochure',
            ])
            ->assertSessionHasErrors('layout');
    }

    public function test_renaming_a_template_re_slugs_it(): void
    {
        $template = ResumeTemplate::factory()->create([
            'name' => 'Old style',
            'slug' => 'old-style',
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('resume-templates.update', $template), $this->validPayload())
            ->assertRedirect(route('resume-templates.index'));

        $this->assertSame('al-mutakamela-house-style', $template->refresh()->slug);
    }

    public function test_an_unused_template_can_be_archived(): void
    {
        $template = ResumeTemplate::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('resume-templates.destroy', $template))
            ->assertRedirect(route('resume-templates.index'));

        $this->assertSoftDeleted($template);
        $this->assertFalse($template->refresh()->is_active);
    }

    public function test_a_template_a_company_uses_cannot_be_archived(): void
    {
        $template = ResumeTemplate::factory()->create();
        Company::factory()->template($template)->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('resume-templates.destroy', $template))
            ->assertForbidden();

        $this->assertNotSoftDeleted($template);
    }

    public function test_an_archived_template_is_no_longer_reachable(): void
    {
        $template = ResumeTemplate::factory()->create();
        $template->delete();

        // Route binding ignores trashed rows, so the edit form is simply gone.
        $this->actingAs(User::factory()->create())
            ->put(route('resume-templates.update', $template), $this->validPayload())
            ->assertNotFound();
    }

    public function test_a_resume_keeps_the_template_it_was_uploaded_with(): void
    {
        $original = ResumeTemplate::factory()->layout(TemplateLayout::Classic)->create();
        $replacement = ResumeTemplate::factory()->layout(TemplateLayout::Modern)->create();

        $company = Company::factory()->template($original)->create();
        $resume = Resume::factory()->parsed()->for($company)->create([
            'resume_template_id' => $original->id,
        ]);

        $company->update(['resume_template_id' => $replacement->id]);

        $this->assertSame($original->id, $resume->refresh()->resume_template_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Al Mutakamela house style',
            'description' => 'Centred header, personal details first.',
            'layout' => TemplateLayout::Professional->value,
            'section_order' => ['summary', 'skills', 'experience'],
            'is_active' => true,
        ];
    }
}
