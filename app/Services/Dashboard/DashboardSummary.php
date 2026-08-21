<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DTOs\Parsing\ParsedResume;
use App\Enums\ResumeStatus;
use App\Models\Company;
use App\Models\Resume;
use App\Models\ResumeTemplate;
use App\Services\Ats\AtsScore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers behind the dashboard.
 *
 * Read-only aggregation, kept out of the controller (ARCHITECTURE §3) and out of
 * the models (RULES §4). Every figure answers a question a recruiter actually
 * has when they open the app: what came in, what is stuck, and what needs me.
 */
final readonly class DashboardSummary
{
    /** How many days the intake chart covers. */
    private const int TREND_DAYS = 14;

    /**
     * How many of the newest parsed resumes the ATS average is taken over.
     * Bounded on purpose: scoring reads `parsed_data`, so an unbounded average
     * would get slower with every upload.
     */
    private const int SCORE_SAMPLE = 100;

    public function __construct(private AtsScore $scores) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $byStatus = $this->countsByStatus();
        $total = array_sum($byStatus);

        return [
            'totals' => $this->totals($byStatus, $total),
            'pipeline' => $this->pipeline($byStatus, $total),
            'trend' => $this->trend(),
            'ats' => $this->ats(),
            'top_companies' => $this->topCompanies(),
            'template_usage' => $this->templateUsage(),
        ];
    }

    /**
     * Resumes that need a person: failed parses first, then anything stuck in
     * the queue.
     *
     * @return Collection<int, Resume>
     */
    public function needsAttention(int $limit = 5): Collection
    {
        return Resume::query()
            ->with([
                // CompanyCardResource names the template, so it is loaded with the
                // company — never lazily, per resume (RULES §6.4).
                'company:id,public_id,slug,name,logo_path,brand_color,resume_template_id,logo_placement,logo_size,is_active',
                'company.resumeTemplate:id,slug,name,layout',
            ])
            ->whereIn('status', [ResumeStatus::Failed, ResumeStatus::Pending, ResumeStatus::Processing])
            // Failed is the actionable one, so it sorts first.
            ->orderByRaw('case when status = ? then 0 else 1 end', [ResumeStatus::Failed->value])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Resume>
     */
    public function recent(int $limit = 6): Collection
    {
        return Resume::query()
            ->with([
                // CompanyCardResource names the template, so it is loaded with the
                // company — never lazily, per resume (RULES §6.4).
                'company:id,public_id,slug,name,logo_path,brand_color,resume_template_id,logo_placement,logo_size,is_active',
                'company.resumeTemplate:id,slug,name,layout',
            ])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    private function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = Resume::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $byStatus = [];

        foreach (ResumeStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $byStatus;
    }

    /**
     * @param  array<string, int>  $byStatus
     * @return array<string, mixed>
     */
    private function totals(array $byStatus, int $total): array
    {
        $activeCompanies = Company::query()->active()->count();
        $companies = Company::query()->count();

        return [
            'companies' => $companies,
            'companies_active' => $activeCompanies,
            'templates' => ResumeTemplate::query()->count(),
            'templates_active' => ResumeTemplate::query()->active()->count(),
            'resumes' => $total,
            'resumes_this_week' => Resume::query()
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count(),
            'parsed' => $byStatus[ResumeStatus::Parsed->value],
            'failed' => $byStatus[ResumeStatus::Failed->value],
            'in_flight' => $byStatus[ResumeStatus::Pending->value] + $byStatus[ResumeStatus::Processing->value],
        ];
    }

    /**
     * Share of documents in each state — the health of the ingest pipeline.
     *
     * @param  array<string, int>  $byStatus
     * @return list<array{status: string, label: string, color: string, count: int, share: int}>
     */
    private function pipeline(array $byStatus, int $total): array
    {
        return array_map(
            static fn (ResumeStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
                'count' => $byStatus[$status->value],
                'share' => $total === 0
                    ? 0
                    : (int) round(($byStatus[$status->value] / $total) * 100),
            ],
            ResumeStatus::cases(),
        );
    }

    /**
     * Uploads per day, oldest first, with empty days kept so the chart has an
     * even baseline.
     *
     * @return list<array{date: string, label: string, count: int}>
     */
    private function trend(): array
    {
        $since = Carbon::today()->subDays(self::TREND_DAYS - 1);

        /** @var array<string, int> $counts */
        $counts = Resume::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day')
            ->all();

        $days = [];

        for ($offset = 0; $offset < self::TREND_DAYS; $offset++) {
            $day = $since->copy()->addDays($offset);
            $key = $day->toDateString();

            $days[] = [
                'date' => $key,
                'label' => $day->format('D j M'),
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Average ATS readiness over the most recent parsed documents, plus how many
     * of them fall in each band.
     *
     * @return array{average: int|null, band: string|null, sampled: int, bands: array<string, int>}
     */
    private function ats(): array
    {
        $documents = Resume::query()
            ->parsed()
            ->whereNotNull('parsed_data')
            ->latest()
            ->limit(self::SCORE_SAMPLE)
            ->pluck('parsed_data');

        $bands = ['strong' => 0, 'fair' => 0, 'weak' => 0];
        $values = [];

        foreach ($documents as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $score = $this->scores->for(ParsedResume::fromArray($payload));
            $values[] = $score['value'];
            $bands[$score['band']]++;
        }

        if ($values === []) {
            return ['average' => null, 'band' => null, 'sampled' => 0, 'bands' => $bands];
        }

        $average = (int) round(array_sum($values) / count($values));

        return [
            'average' => $average,
            'band' => AtsScore::band($average),
            'sampled' => count($values),
            'bands' => $bands,
        ];
    }

    /**
     * Busiest clients. Eager counting, never a query per row (RULES §6.4).
     *
     * @return list<array{name: string, slug: string, resumes: int}>
     */
    private function topCompanies(): array
    {
        return array_values(Company::query()
            ->select(['id', 'name', 'slug'])
            ->withCount('resumes')
            // withCount is a subselect, not a GROUP BY, so the filter is a
            // whereHas — HAVING on it is invalid SQL.
            ->whereHas('resumes')
            ->orderByDesc('resumes_count')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(static fn (Company $company): array => [
                'name' => $company->name,
                'slug' => $company->slug,
                'resumes' => (int) $company->resumes_count,
            ])
            ->all());
    }

    /**
     * Which house styles are actually in use.
     *
     * @return list<array{name: string, slug: string, companies: int, resumes: int}>
     */
    private function templateUsage(): array
    {
        return array_values(ResumeTemplate::query()
            ->select(['id', 'name', 'slug'])
            ->withCount(['companies', 'resumes'])
            ->orderByDesc('resumes_count')
            ->orderByDesc('companies_count')
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(static fn (ResumeTemplate $template): array => [
                'name' => $template->name,
                'slug' => $template->slug,
                'companies' => (int) $template->companies_count,
                'resumes' => (int) $template->resumes_count,
            ])
            ->all());
    }
}
