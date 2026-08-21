<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Parsing\ParsedResume;
use App\Enums\LogoPlacement;
use App\Enums\LogoSize;
use App\Enums\ResumeTemplate;
use App\Models\Company;
use App\Services\Ats\AtsResumeFormatter;
use App\Services\Parsing\FakeResumeParser;
use Tests\TestCase;

final class AtsResumeFormatterTest extends TestCase
{
    public function test_sections_follow_the_template_default_order(): void
    {
        $document = $this->format(new Company([
            'resume_template' => ResumeTemplate::Modern,
            'section_order' => null,
            'brand_color' => '#000000',
            'logo_placement' => LogoPlacement::Right,
            'logo_size' => LogoSize::Medium,
        ]));

        $this->assertSame(
            ['summary', 'skills', 'experience', 'certifications', 'education', 'languages'],
            array_column($document['sections'], 'key'),
        );
    }

    public function test_a_company_override_wins_over_the_template(): void
    {
        $document = $this->format(new Company([
            'resume_template' => ResumeTemplate::Classic,
            'section_order' => ['experience', 'summary', 'skills'],
            'brand_color' => '#000000',
            'logo_placement' => LogoPlacement::Right,
            'logo_size' => LogoSize::Medium,
        ]));

        $this->assertSame(
            ['experience', 'summary', 'skills'],
            array_column($document['sections'], 'key'),
        );
    }

    public function test_unknown_section_keys_are_ignored(): void
    {
        $document = $this->format(new Company([
            'resume_template' => ResumeTemplate::Classic,
            'section_order' => ['summary', 'salary_expectations', 'skills'],
            'brand_color' => '#000000',
            'logo_placement' => LogoPlacement::Right,
            'logo_size' => LogoSize::Medium,
        ]));

        $this->assertSame(['summary', 'skills'], array_column($document['sections'], 'key'));
    }

    public function test_dates_are_humanised_and_current_roles_stay_open(): void
    {
        $document = $this->format($this->company());

        $experience = $this->section($document, 'experience');

        $this->assertSame('Mar 2021 — Present', $experience['entries'][0]['period']);
        $this->assertSame('Jan 2018 — Feb 2021', $experience['entries'][1]['period']);
    }

    public function test_decorative_bullets_and_smart_quotes_are_stripped_for_ats_safety(): void
    {
        $parsed = ParsedResume::fromArray([
            ...FakeResumeParser::payload(),
            'summary' => "•  Led  the \u{201C}regional\u{201D} team \u{2014} across   three sites",
        ]);

        $document = (new AtsResumeFormatter)->format($parsed, $this->company());

        $this->assertSame(
            'Led the "regional" team - across three sites',
            $this->section($document, 'summary')['text'],
        );
    }

    public function test_a_complete_resume_scores_strong(): void
    {
        $document = $this->format($this->company());

        $this->assertSame(100, $document['score']['value']);
        $this->assertSame('strong', $document['score']['band']);
        $this->assertSame([], $document['score']['notes']);
    }

    public function test_a_missing_email_and_experience_drive_the_score_down(): void
    {
        $payload = FakeResumeParser::payload();
        $payload['contact']['email'] = null;
        $payload['experience'] = [];

        $document = (new AtsResumeFormatter)->format(
            ParsedResume::fromArray($payload),
            $this->company(),
        );

        $this->assertSame(55, $document['score']['value']);
        $this->assertSame('weak', $document['score']['band']);
        $this->assertNotEmpty($document['score']['notes']);
    }

    public function test_empty_sections_are_omitted_rather_than_rendered_blank(): void
    {
        $payload = FakeResumeParser::payload();
        $payload['certifications'] = [];
        $payload['languages'] = [];

        $document = (new AtsResumeFormatter)->format(
            ParsedResume::fromArray($payload),
            $this->company(),
        );

        $keys = array_column($document['sections'], 'key');

        $this->assertNotContains('certifications', $keys);
        $this->assertNotContains('languages', $keys);
    }

    public function test_the_professional_template_centres_the_header_and_leads_with_details(): void
    {
        $document = (new AtsResumeFormatter)->format(
            ParsedResume::fromArray($this->detailedPayload()),
            $this->company(ResumeTemplate::Professional),
        );

        $this->assertTrue($document['header']['centred']);
        $this->assertSame('Senior Full-Stack Developer', $document['header']['headline']);
        $this->assertSame('details', $document['sections'][0]['key']);
    }

    public function test_other_templates_keep_a_left_aligned_header(): void
    {
        $document = $this->format($this->company(ResumeTemplate::Classic));

        $this->assertFalse($document['header']['centred']);
    }

    public function test_personal_details_render_as_labelled_rows(): void
    {
        $document = (new AtsResumeFormatter)->format(
            ParsedResume::fromArray($this->detailedPayload()),
            $this->company(ResumeTemplate::Professional),
        );

        $details = $this->section($document, 'details');

        $this->assertSame('details', $details['type']);
        $this->assertSame(
            [['label' => 'Date of Birth', 'value' => 'June 24, 1997']],
            $details['rows'],
        );
    }

    public function test_labelled_skills_render_as_groups_and_keep_a_flat_list(): void
    {
        $document = (new AtsResumeFormatter)->format(
            ParsedResume::fromArray($this->detailedPayload()),
            $this->company(ResumeTemplate::Professional),
        );

        $skills = $this->section($document, 'skills');

        $this->assertSame('skill_groups', $skills['type']);
        $this->assertSame('Databases', $skills['groups'][0]['label']);
        $this->assertSame(['MySQL', 'PostgreSQL'], $skills['groups'][0]['items']);
        $this->assertSame(['MySQL', 'PostgreSQL'], $skills['items']);
    }

    public function test_unlabelled_skills_still_render_as_a_tag_cloud(): void
    {
        $skills = $this->section($this->format($this->company()), 'skills');

        $this->assertSame('tags', $skills['type']);
    }

    public function test_the_logo_carries_the_company_placement_and_size(): void
    {
        $company = $this->company();
        $company->logo_path = 'company-logos/acme.png';
        $company->logo_placement = LogoPlacement::Centre;
        $company->logo_size = LogoSize::Large;

        $document = $this->format($company);

        $this->assertSame(
            ['placement' => 'centre', 'size' => 'large', 'pixels' => 72],
            $document['header']['logo'],
        );
    }

    public function test_a_hidden_or_absent_logo_yields_no_letterhead(): void
    {
        $hidden = $this->company();
        $hidden->logo_path = 'company-logos/acme.png';
        $hidden->logo_placement = LogoPlacement::Hidden;

        $this->assertNull($this->format($hidden)['header']['logo']);

        // No uploaded logo at all — placement is irrelevant.
        $none = $this->company();
        $none->logo_path = null;
        $none->logo_placement = LogoPlacement::Left;

        $this->assertNull($this->format($none)['header']['logo']);
    }

    /**
     * A resume whose parser sent only a flat skills list (no groups) still renders.
     */
    public function test_a_flat_skill_list_is_upgraded_to_one_unlabelled_group(): void
    {
        $payload = FakeResumeParser::payload();
        unset($payload['skill_groups']);

        $parsed = ParsedResume::fromArray($payload);

        $this->assertCount(1, $parsed->skillGroups);
        $this->assertNull($parsed->skillGroups[0]->label);
        $this->assertSame($parsed->skills, $parsed->skillGroups[0]->items);
    }

    /**
     * @return array<string, mixed>
     */
    private function detailedPayload(): array
    {
        return [
            ...FakeResumeParser::payload(),
            'headline' => 'Senior Full-Stack Developer',
            'details' => [['label' => 'Date of Birth', 'value' => 'June 24, 1997']],
            'skill_groups' => [['label' => 'Databases', 'items' => ['MySQL', 'PostgreSQL']]],
            'skills' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function format(Company $company): array
    {
        return (new AtsResumeFormatter)->format(
            ParsedResume::fromArray(FakeResumeParser::payload()),
            $company,
        );
    }

    private function company(ResumeTemplate $template = ResumeTemplate::Classic): Company
    {
        return new Company([
            'name' => 'Gulf Freight Partners',
            'resume_template' => $template,
            'section_order' => null,
            'brand_color' => '#0F766E',
            'logo_placement' => LogoPlacement::Right,
            'logo_size' => LogoSize::Medium,
        ]);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function section(array $document, string $key): array
    {
        foreach ($document['sections'] as $section) {
            if ($section['key'] === $key) {
                return $section;
            }
        }

        $this->fail("Section [{$key}] is missing from the ATS document.");
    }
}
