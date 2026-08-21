<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Enums\LogoPlacement;
use App\Enums\LogoSize;
use App\Enums\ResumeTemplate;
use App\Models\Company;
use App\Models\User;
use App\Policies\CompanyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_the_company_list(): void
    {
        $this->get(route('companies.index'))->assertRedirect(route('login'));
    }

    public function test_the_index_lists_companies_with_a_resume_count(): void
    {
        $company = Company::factory()->create(['name' => 'Gulf Freight Partners']);

        $this->actingAs(User::factory()->create())
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee($company->name);
    }

    public function test_a_company_is_created_with_a_slug_and_a_re_encoded_logo(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->create())
            ->post(route('companies.store'), [
                ...$this->validPayload(),
                'logo' => UploadedFile::fake()->image('logo.jpg', 300, 300),
            ]);

        $company = Company::query()->firstOrFail();

        $response->assertRedirect(route('companies.show', $company));
        $this->assertSame('nakheel-engineering', $company->slug);
        $this->assertSame(ResumeTemplate::Modern, $company->resume_template);

        // RULES §5.7: random filename, and the bitmap is re-encoded to PNG.
        $this->assertNotNull($company->logo_path);
        $this->assertStringEndsWith('.png', $company->logo_path);
        $this->assertStringNotContainsString('logo.jpg', $company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_duplicate_names_get_distinct_slugs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('companies.store'), $this->validPayload());
        $this->actingAs($user)->post(route('companies.store'), $this->validPayload());

        $this->assertSame(
            ['nakheel-engineering', 'nakheel-engineering-2'],
            Company::query()->orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_an_svg_logo_is_rejected_because_it_cannot_be_re_encoded(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('companies.store'), [
                ...$this->validPayload(),
                'logo' => UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertSame(0, Company::query()->count());
    }

    public function test_a_malformed_brand_colour_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('companies.store'), [
                ...$this->validPayload(),
                'brand_color' => 'teal',
            ])
            ->assertSessionHasErrors('brand_color');
    }

    public function test_an_unknown_section_key_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('companies.store'), [
                ...$this->validPayload(),
                'section_order' => ['summary', 'salary_expectations'],
            ])
            ->assertSessionHasErrors('section_order.1');
    }

    public function test_renaming_a_company_re_slugs_it_and_replaces_the_logo(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'logo_path' => 'company-logos/old.png',
        ]);
        Storage::disk('public')->put('company-logos/old.png', 'x');

        $this->actingAs(User::factory()->create())
            ->put(route('companies.update', $company), [
                ...$this->validPayload(),
                'name' => 'Brand New Name',
                'logo' => UploadedFile::fake()->image('new.png', 200, 200),
            ])
            ->assertRedirect();

        $company->refresh();

        $this->assertSame('brand-new-name', $company->slug);
        $this->assertNotSame('company-logos/old.png', $company->logo_path);
        Storage::disk('public')->assertMissing('company-logos/old.png');
    }

    public function test_a_logo_can_be_removed_without_uploading_a_replacement(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create(['logo_path' => 'company-logos/keep.png']);
        Storage::disk('public')->put('company-logos/keep.png', 'x');

        $this->actingAs(User::factory()->create())
            ->put(route('companies.update', $company), [
                ...$this->validPayload(),
                'name' => $company->name,
                'remove_logo' => true,
            ])
            ->assertRedirect();

        $this->assertNull($company->refresh()->logo_path);
        Storage::disk('public')->assertMissing('company-logos/keep.png');
    }

    public function test_deleting_a_company_archives_it_instead_of_erasing_it(): void
    {
        $company = Company::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('companies.destroy', $company))
            ->assertRedirect(route('companies.index'));

        $this->assertSoftDeleted($company);
        $this->assertFalse($company->fresh()->is_active);
    }

    /**
     * An archived company is invisible to route binding, so it 404s before it ever
     * reaches the policy.
     */
    public function test_an_archived_company_cannot_be_updated(): void
    {
        $company = Company::factory()->create();
        $company->delete();

        $this->actingAs(User::factory()->create())
            ->put(route('companies.update', $company), $this->validPayload())
            ->assertNotFound();
    }

    /**
     * The policy is the second gate. Asserted directly because the Spatie-disabled
     * `Gate::before` bypass would mask it through the HTTP layer (CLAUDE.md §5).
     */
    public function test_the_policy_forbids_mutating_an_archived_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->delete();

        $policy = new CompanyPolicy;

        $this->assertFalse($policy->update($user, $company->fresh()));
        $this->assertFalse($policy->delete($user, $company->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Nakheel Engineering',
            'industry' => 'Construction',
            'contact_email' => 'HR@Nakheeleng.ae',
            'contact_phone' => '+971 24 447788',
            'website' => 'https://nakheeleng.example.ae',
            'brand_color' => '#1d4ed8',
            'resume_template' => ResumeTemplate::Modern->value,
            'logo_placement' => LogoPlacement::Right->value,
            'logo_size' => LogoSize::Medium->value,
            'formatting_notes' => 'Certifications before education.',
            'is_active' => true,
        ];
    }
}
