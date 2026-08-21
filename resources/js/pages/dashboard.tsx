import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    FileText,
    Gauge,
    LayoutTemplate,
    Plus,
    RefreshCw,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { useEffect } from 'react';
import { EmptyState } from '@/components/crm/empty-state';
import { ResumeRow } from '@/components/crm/resume-row';
import { StatTile } from '@/components/crm/stat-tile';
import { UploadTrend } from '@/components/crm/upload-trend';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import companies from '@/routes/companies';
import resumeTemplates from '@/routes/resume-templates';
import type { DashboardSummary, ResumeCard } from '@/types/models';

type Props = {
    summary: DashboardSummary;
    attention: ResumeCard[];
    recent: ResumeCard[];
};

const BAND_TONE = {
    strong: 'text-emerald-600 dark:text-emerald-400',
    fair: 'text-amber-600 dark:text-amber-400',
    weak: 'text-red-600 dark:text-red-400',
} as const;

const PIPELINE_TONE = {
    neutral: 'bg-muted-foreground/40',
    info: 'bg-blue-500',
    success: 'bg-emerald-500',
    danger: 'bg-red-500',
} as const;

export default function Dashboard({ summary, attention, recent }: Props) {
    const { totals, pipeline, trend, ats } = summary;
    const nothingYet = totals.resumes === 0 && totals.companies === 0;

    // Documents in the queue resolve on their own; keep the page honest while
    // they do (the same 3s cadence the preview page uses).
    useEffect(() => {
        if (totals.in_flight === 0) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['summary', 'attention', 'recent'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [totals.in_flight]);

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Dashboard</h1>
                        <p className="text-sm text-muted-foreground">
                            Resume intake across every client company.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={resumeTemplates.index()}>
                                <LayoutTemplate className="size-4" aria-hidden />
                                Templates
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={companies.index()}>
                                <Upload className="size-4" aria-hidden />
                                Upload a resume
                            </Link>
                        </Button>
                    </div>
                </div>

                {nothingYet ? (
                    <EmptyState
                        icon={Building2}
                        title="Nothing to show yet"
                        description="Add your first client company, then upload a candidate PDF — this page fills in as documents come through."
                        action={
                            <Button asChild>
                                <Link href={companies.create()}>
                                    <Plus className="size-4" aria-hidden />
                                    Add company
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <StatTile
                                label="Resumes"
                                value={totals.resumes}
                                hint={`${totals.resumes_this_week} in the last 7 days`}
                                icon={FileText}
                            />
                            <StatTile
                                label="Needs attention"
                                value={totals.failed}
                                hint={
                                    totals.in_flight > 0
                                        ? `${totals.in_flight} still in the queue`
                                        : 'Failed parses to retry'
                                }
                                icon={TriangleAlert}
                                tone={
                                    totals.failed > 0 ? 'attention' : 'default'
                                }
                            />
                            <StatTile
                                label="Companies"
                                value={totals.companies_active}
                                hint={
                                    totals.companies === totals.companies_active
                                        ? 'All active'
                                        : `${totals.companies - totals.companies_active} archived`
                                }
                                icon={Building2}
                            />
                            <StatTile
                                label="Templates"
                                value={totals.templates_active}
                                hint={`${totals.templates} in total`}
                                icon={LayoutTemplate}
                            />
                        </div>

                        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                            <div className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Uploads · last 14 days
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <UploadTrend days={trend} />
                                    </CardContent>
                                </Card>

                                {attention.length > 0 && (
                                    <Card className="border-red-500/30">
                                        <CardHeader className="flex-row items-center justify-between gap-2">
                                            <CardTitle>
                                                Needs attention
                                            </CardTitle>
                                            <Badge variant="secondary">
                                                {attention.length}
                                            </Badge>
                                        </CardHeader>
                                        <CardContent>
                                            <ul className="divide-y">
                                                {attention.map((resume) => (
                                                    <ResumeRow
                                                        key={resume.id}
                                                        resume={resume}
                                                        showReason
                                                    />
                                                ))}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Latest uploads</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {recent.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No resumes uploaded yet.
                                            </p>
                                        ) : (
                                            <ul className="divide-y">
                                                {recent.map((resume) => (
                                                    <ResumeRow
                                                        key={resume.id}
                                                        resume={resume}
                                                    />
                                                ))}
                                            </ul>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>

                            <aside className="space-y-6">
                                <Card>
                                    <CardHeader className="flex-row items-center gap-2">
                                        <Gauge
                                            className="size-4 text-muted-foreground"
                                            aria-hidden
                                        />
                                        <CardTitle>
                                            Average ATS readiness
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {ats.average === null ? (
                                            <p className="text-sm text-muted-foreground">
                                                No parsed resumes to score yet.
                                            </p>
                                        ) : (
                                            <>
                                                <div className="flex items-baseline gap-2">
                                                    <span
                                                        className={cn(
                                                            'text-3xl font-semibold tabular-nums',
                                                            ats.band !== null &&
                                                                BAND_TONE[
                                                                    ats.band
                                                                ],
                                                        )}
                                                    >
                                                        {ats.average}
                                                    </span>
                                                    <span className="text-sm text-muted-foreground">
                                                        / 100 · {ats.band}
                                                    </span>
                                                </div>
                                                <ul className="space-y-1 text-sm text-muted-foreground">
                                                    <li>
                                                        {ats.bands.strong}{' '}
                                                        strong ·{' '}
                                                        {ats.bands.fair} fair ·{' '}
                                                        {ats.bands.weak} weak
                                                    </li>
                                                    <li className="text-xs">
                                                        Across the{' '}
                                                        {ats.sampled} most
                                                        recent parsed documents.
                                                    </li>
                                                </ul>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Parse pipeline</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2.5">
                                        {pipeline.map((stage) => (
                                            <div
                                                key={stage.status}
                                                className="space-y-1"
                                            >
                                                <div className="flex items-center justify-between text-sm">
                                                    <span>{stage.label}</span>
                                                    <span className="text-muted-foreground tabular-nums">
                                                        {stage.count} ·{' '}
                                                        {stage.share}%
                                                    </span>
                                                </div>
                                                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            PIPELINE_TONE[
                                                                stage.color
                                                            ],
                                                        )}
                                                        style={{
                                                            width: `${stage.share}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>

                                {summary.top_companies.length > 0 && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Busiest companies
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <ul className="space-y-2 text-sm">
                                                {summary.top_companies.map(
                                                    (company) => (
                                                        <li
                                                            key={company.slug}
                                                            className="flex items-center justify-between gap-2"
                                                        >
                                                            <Link
                                                                href={companies.show(
                                                                    company.slug,
                                                                )}
                                                                className="truncate hover:underline"
                                                            >
                                                                {company.name}
                                                            </Link>
                                                            <span className="text-muted-foreground tabular-nums">
                                                                {
                                                                    company.resumes
                                                                }
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}

                                {summary.template_usage.length > 0 && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Template usage
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <ul className="space-y-2 text-sm">
                                                {summary.template_usage.map(
                                                    (template) => (
                                                        <li
                                                            key={template.slug}
                                                            className="flex items-center justify-between gap-2"
                                                        >
                                                            <Link
                                                                href={resumeTemplates.edit(
                                                                    template.slug,
                                                                )}
                                                                className="truncate hover:underline"
                                                            >
                                                                {template.name}
                                                            </Link>
                                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                                {
                                                                    template.companies
                                                                }{' '}
                                                                co ·{' '}
                                                                {
                                                                    template.resumes
                                                                }{' '}
                                                                cv
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                )}

                                {totals.in_flight > 0 && (
                                    <p
                                        className="flex items-center gap-2 text-xs text-muted-foreground"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        <RefreshCw
                                            className="size-3.5 animate-spin"
                                            aria-hidden
                                        />
                                        {totals.in_flight} document
                                        {totals.in_flight === 1 ? '' : 's'} in
                                        the queue — this page refreshes itself.
                                    </p>
                                )}
                            </aside>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
