<?php

declare(strict_types=1);

namespace App\Services\Parsing;

use App\Contracts\ResumeParser;
use App\DTOs\Parsing\ParsedResume;
use Illuminate\Support\Facades\Log;

/**
 * Deterministic parser for tests and for running the app without the Python
 * sidecar (SIDECAR_DRIVER=fake). Same output shape as the real service.
 */
final readonly class FakeResumeParser implements ResumeParser
{
    public function parse(string $storedPath, string $originalFilename): ParsedResume
    {
        // Outside tests this is almost always a misconfiguration: the operator
        // thinks they are parsing a real document. Say so in the log.
        if (! app()->runningUnitTests()) {
            Log::warning('resume.fake_parser_used', [
                'reason' => 'SIDECAR_DRIVER is not "sidecar" — returning SAMPLE data, not the uploaded file.',
                'driver' => config('services.sidecar.driver'),
            ]);
        }

        return ParsedResume::fromArray(self::payload());
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        return [
            'contact' => [
                'full_name' => 'Layla Haddad (sample)',
                'email' => 'layla.haddad@example.com',
                'phone' => '+971501234567',
                'location' => 'Dubai, United Arab Emirates',
                'linkedin' => 'linkedin.com/in/layla-haddad',
                'website' => null,
            ],
            'summary' => 'Operations manager with 8 years in regional logistics, leading cross-border '
                .'fulfilment for GCC retail accounts and cutting delivery cost per order by 22%.',
            'experience' => [
                [
                    'title' => 'Regional Operations Manager',
                    'company' => 'Gulf Freight Partners',
                    'location' => 'Dubai, UAE',
                    'start_date' => '2021-03',
                    'end_date' => null,
                    'is_current' => true,
                    'highlights' => [
                        'Owned a 42-person warehouse and last-mile team across three emirates.',
                        'Cut cost per delivered order by 22% by renegotiating 3PL contracts.',
                        'Launched a returns hub that recovered AED 1.8M of stock in year one.',
                    ],
                ],
                [
                    'title' => 'Logistics Supervisor',
                    'company' => 'Almasa Retail Group',
                    'location' => 'Sharjah, UAE',
                    'start_date' => '2018-01',
                    'end_date' => '2021-02',
                    'is_current' => false,
                    'highlights' => [
                        'Scheduled inbound freight for 60+ retail outlets.',
                        'Introduced cycle counting that lifted inventory accuracy to 99.1%.',
                    ],
                ],
            ],
            'education' => [
                [
                    'degree' => 'BSc Supply Chain Management',
                    'institution' => 'American University of Sharjah',
                    'location' => 'Sharjah, UAE',
                    'start_date' => '2013-09',
                    'end_date' => '2017-06',
                ],
            ],
            'skills' => [
                'Supply chain planning', 'Vendor negotiation', 'WMS (Manhattan)', 'SAP MM',
                'Team leadership', 'Cost modelling', 'Power BI', 'Arabic / English',
            ],
            'certifications' => ['APICS CSCP (2022)', 'Lean Six Sigma Green Belt'],
            'languages' => ['Arabic — native', 'English — fluent'],
            'warnings' => [
                // Loudly flagged: whoever sees this document is NOT looking at their
                // upload. Set SIDECAR_DRIVER=sidecar to parse the real file.
                'SAMPLE DATA — the parsing sidecar is not connected (SIDECAR_DRIVER=fake), '
                    .'so this document does not come from the uploaded PDF.',
                'Source PDF used a two-column layout; reading order was reconstructed.',
            ],
            'page_count' => 2,
            'parser_version' => 'fake-1.0',
        ];
    }
}
