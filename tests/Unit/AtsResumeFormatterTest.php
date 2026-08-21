<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Parsing\ParsedResume;
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

    private function company(): Company
    {
        return new Company([
            'name' => 'Gulf Freight Partners',
            'resume_template' => ResumeTemplate::Classic,
            'section_order' => null,
            'brand_color' => '#0F766E',
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
