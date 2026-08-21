<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\DTOs\Parsing\ExperienceEntry;
use App\DTOs\Parsing\ParsedResume;

/**
 * ATS readiness for one parsed resume: 0–100 with a band and actionable notes
 * (PRD BR-7).
 *
 * Its own class because two callers need the same numbers — AtsResumeFormatter
 * puts them on the preview, and the dashboard averages them across recent
 * uploads. Depends on nothing but the parsed document, so it is cheap to run in
 * a loop.
 */
final readonly class AtsScore
{
    /**
     * @return array{value: int, band: string, notes: list<string>}
     */
    public function for(ParsedResume $resume): array
    {
        $notes = [];
        $value = 100;

        if ($resume->contact->email === null) {
            $value -= 20;
            $notes[] = __('No email address was detected — ATS filters usually reject the file outright.');
        }

        if ($resume->contact->phone === null) {
            $value -= 10;
            $notes[] = __('No phone number was detected.');
        }

        if ($resume->summary === null) {
            $value -= 10;
            $notes[] = __('No professional summary — add 2–3 lines with the target job title.');
        }

        if ($resume->experience === []) {
            $value -= 25;
            $notes[] = __('No work experience could be extracted; the layout may be unreadable to an ATS.');
        }

        if (count($resume->skills) < 5) {
            $value -= 10;
            $notes[] = __('Fewer than five skills detected — ATS keyword matching needs an explicit skills list.');
        }

        $quantified = array_filter(
            $resume->experience,
            static fn (ExperienceEntry $entry): bool => array_any(
                $entry->highlights,
                static fn (string $highlight): bool => preg_match('/\d/', $highlight) === 1,
            ),
        );

        if ($resume->experience !== [] && $quantified === []) {
            $value -= 5;
            $notes[] = __('No quantified achievements found — add numbers (%, AED, headcount) to highlights.');
        }

        $value = max(0, min(100, $value));

        return [
            'value' => $value,
            'band' => self::band($value),
            'notes' => $notes,
        ];
    }

    /**
     * The band a score falls in. Public so an average can be banded without
     * re-deriving the thresholds.
     */
    public static function band(int $value): string
    {
        return match (true) {
            $value >= 85 => 'strong',
            $value >= 65 => 'fair',
            default => 'weak',
        };
    }
}
