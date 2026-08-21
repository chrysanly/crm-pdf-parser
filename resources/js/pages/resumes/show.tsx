import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileDown,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { AtsResumePreview } from '@/components/crm/ats-resume-preview';
import { ResumeStatusBadge } from '@/components/crm/resume-status-badge';
import { ResumeTemplateSwitcher } from '@/components/crm/resume-template-switcher';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import companies from '@/routes/companies';
import resumes from '@/routes/resumes';
import type { Resume, ResumeTemplateCard } from '@/types/models';

type Props = {
    resume: Resume;
    canDownload: boolean;
    templates: ResumeTemplateCard[];
};

const BAND_TONE = {
    strong: 'text-emerald-600 dark:text-emerald-400',
    fair: 'text-amber-600 dark:text-amber-400',
    weak: 'text-red-600 dark:text-red-400',
} as const;

export default function ResumeShow({
    resume,
    canDownload,
    templates,
}: Props) {
    const inFlight =
        resume.status === 'pending' || resume.status === 'processing';
    const [exporting, setExporting] = useState(false);
    const [retrying, setRetrying] = useState(false);

    useEffect(() => {
        if (!inFlight) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['resume'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [inFlight]);

    return (
        <>
            <Head title={resume.candidate_name ?? resume.original_filename} />

            <div className="flex flex-col gap-6 p-4 print:p-0">
                <div className="flex flex-wrap items-start justify-between gap-3 print:hidden">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {resume.candidate_name ??
                                    resume.original_filename}
                            </h1>
                            <ResumeStatusBadge
                                status={resume.status}
                                label={resume.status_label}
                                color={resume.status_color}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Formatted for{' '}
                            <Link
                                href={companies.show(resume.company.slug)}
                                className="font-medium hover:underline"
                            >
                                {resume.company.name}
                            </Link>{' '}
                            · {resume.resume_template ??
                                resume.company.resume_template_name}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="ghost" asChild>
                            <Link href={companies.show(resume.company.slug)}>
                                <ArrowLeft
                                    className="size-4 rtl:rotate-180"
                                    aria-hidden
                                />
                                Back
                            </Link>
                        </Button>
                        {canDownload && (
                            <Button variant="outline" asChild>
                                <a href={resumes.file.url(resume.id)}>
                                    <Download className="size-4" aria-hidden />
                                    Original PDF
                                </a>
                            </Button>
                        )}
                        {resume.ats !== null && (
                            // A real download, not window.print(): the server
                            // renders the ATS document — and only that — through
                            // dompdf, so the text stays selectable text.
                            <Button asChild aria-busy={exporting}>
                                {/* A plain download has no completion event, so
                                    the button holds its busy state briefly and
                                    blocks pointer events instead of using
                                    `disabled`, which an anchor ignores. */}
                                <a
                                    href={resumes.pdf.url(resume.id)}
                                    aria-disabled={exporting}
                                    className={
                                        exporting
                                            ? 'pointer-events-none opacity-80'
                                            : undefined
                                    }
                                    onClick={() => {
                                        setExporting(true);
                                        window.setTimeout(
                                            () => setExporting(false),
                                            2500,
                                        );
                                    }}
                                >
                                    {exporting ? (
                                        <Spinner />
                                    ) : (
                                        <FileDown
                                            className="size-4"
                                            aria-hidden
                                        />
                                    )}
                                    {exporting
                                        ? 'Preparing PDF…'
                                        : 'Download PDF'}
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Loading state: parsing is queued, so the document isn't here yet. */}
                {inFlight && (
                    <Card className="print:hidden">
                        <CardContent className="space-y-3 py-6">
                            <p
                                className="text-sm text-muted-foreground"
                                role="status"
                                aria-live="polite"
                            >
                                Parsing {resume.original_filename} — this page
                                updates itself when the document is ready.
                            </p>
                            <Skeleton className="h-6 w-1/3" />
                            <Skeleton className="h-4 w-2/3" />
                            <Skeleton className="h-4 w-1/2" />
                            <Skeleton className="h-24 w-full" />
                        </CardContent>
                    </Card>
                )}

                {/* Error state. */}
                {resume.status === 'failed' && (
                    <Card className="border-red-500/40 print:hidden">
                        <CardHeader className="flex-row items-center gap-2">
                            <TriangleAlert
                                className="size-4 text-red-600"
                                aria-hidden
                            />
                            <CardTitle>Parsing failed</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                {resume.failure_reason ??
                                    'The parser could not read this document.'}
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={retrying || inFlight}
                                aria-busy={retrying}
                                onClick={() => {
                                    setRetrying(true);
                                    router.post(
                                        resumes.reparse.url(resume.id),
                                        {},
                                        {
                                            preserveScroll: true,
                                            onFinish: () => setRetrying(false),
                                        },
                                    );
                                }}
                            >
                                {retrying ? (
                                    <Spinner />
                                ) : (
                                    <RefreshCw className="size-4" aria-hidden />
                                )}
                                {retrying ? 'Queueing…' : 'Try again'}
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {resume.ats !== null && (
                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] print:block">
                        <AtsResumePreview
                            document={resume.ats}
                            company={resume.company}
                        />

                        <aside className="space-y-6 print:hidden">
                            <ResumeTemplateSwitcher
                                resume={resume}
                                templates={templates}
                                busy={inFlight}
                            />

                            <Card>
                                <CardHeader>
                                    <CardTitle>ATS readiness</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex items-baseline gap-2">
                                        <span
                                            className={`text-3xl font-semibold ${BAND_TONE[resume.ats.score.band]}`}
                                        >
                                            {resume.ats.score.value}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            / 100 · {resume.ats.score.band}
                                        </span>
                                    </div>
                                    {resume.ats.score.notes.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No ATS problems found in this
                                            resume.
                                        </p>
                                    ) : (
                                        <ul className="list-disc space-y-1.5 ps-5 text-sm text-muted-foreground">
                                            {resume.ats.score.notes.map(
                                                (note) => (
                                                    <li key={note}>{note}</li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>

                            {resume.ats.warnings.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Parser notes</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ul className="list-disc space-y-1.5 ps-5 text-sm text-muted-foreground">
                                            {resume.ats.warnings.map(
                                                (warning) => (
                                                    <li key={warning}>
                                                        {warning}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </CardContent>
                                </Card>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle>Source document</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm text-muted-foreground">
                                    <p className="break-all">
                                        {resume.original_filename}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {resume.file_size_kb} KB
                                        </Badge>
                                        {resume.page_count !== null && (
                                            <Badge variant="outline">
                                                {resume.page_count} pages
                                            </Badge>
                                        )}
                                    </div>
                                    {resume.candidate_email !== null && (
                                        <p className="break-all">
                                            {resume.candidate_email}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </aside>
                    </div>
                )}
            </div>
        </>
    );
}

ResumeShow.layout = {
    breadcrumbs: [{ title: 'Companies', href: companies.index() }],
};
