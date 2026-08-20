import { Head, Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    Mail,
    Pencil,
    Phone,
    RefreshCw,
} from 'lucide-react';
import { useEffect } from 'react';
import { CompanyLogo } from '@/components/crm/company-logo';
import { EmptyState } from '@/components/crm/empty-state';
import { ResumeStatusBadge } from '@/components/crm/resume-status-badge';
import { ResumeUploadCard } from '@/components/crm/resume-upload-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import companies from '@/routes/companies';
import resumes from '@/routes/resumes';
import type { Company, Paginated, ResumeCard } from '@/types/models';

type Props = {
    company: Company;
    resumes: Paginated<ResumeCard>;
};

const SECTION_LABELS: Record<string, string> = {
    summary: 'Summary',
    experience: 'Experience',
    education: 'Education',
    skills: 'Skills',
    certifications: 'Certifications',
    languages: 'Languages',
};

export default function CompanyShow({ company, resumes: page }: Props) {
    const hasWorkInFlight = page.data.some(
        (resume) => resume.status === 'pending' || resume.status === 'processing',
    );

    // Parsing runs on the queue, so poll only while something is actually in
    // flight, and only for the list (partial reload).
    useEffect(() => {
        if (!hasWorkInFlight) return;

        const timer = window.setInterval(() => {
            router.reload({ only: ['resumes'] });
        }, 4000);

        return () => window.clearInterval(timer);
    }, [hasWorkInFlight]);

    return (
        <>
            <Head title={company.name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <CompanyLogo
                            name={company.name}
                            logoUrl={company.logo_url}
                            brandColor={company.brand_color}
                            className="size-14"
                        />
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-semibold">
                                    {company.name}
                                </h1>
                                {!company.is_active && (
                                    <Badge variant="secondary">Inactive</Badge>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {company.industry ?? 'No industry set'} ·{' '}
                                {company.resume_template_label}
                            </p>
                            <div className="flex flex-wrap gap-x-4 gap-y-1 pt-1 text-sm text-muted-foreground">
                                {company.contact_email !== null && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Mail className="size-3.5" aria-hidden />
                                        {company.contact_email}
                                    </span>
                                )}
                                {company.contact_phone !== null && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Phone className="size-3.5" aria-hidden />
                                        {company.contact_phone}
                                    </span>
                                )}
                                {company.website !== null && (
                                    <a
                                        href={company.website}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="inline-flex items-center gap-1.5 hover:underline"
                                    >
                                        <ExternalLink
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                        Website
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={companies.edit(company.slug)}>
                            <Pencil className="size-4" aria-hidden />
                            Edit company
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between">
                                <CardTitle>
                                    Resumes ({page.meta.total})
                                </CardTitle>
                                {hasWorkInFlight && (
                                    <span
                                        className="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        <RefreshCw
                                            className="size-3.5 animate-spin"
                                            aria-hidden
                                        />
                                        Parsing in progress
                                    </span>
                                )}
                            </CardHeader>
                            <CardContent>
                                {page.data.length === 0 ? (
                                    <EmptyState
                                        icon={FileText}
                                        title="No resumes yet"
                                        description={`Upload a candidate PDF and it will come back formatted in ${company.name}’s house style.`}
                                    />
                                ) : (
                                    <ul className="divide-y">
                                        {page.data.map((resume) => (
                                            <li
                                                key={resume.id}
                                                className="flex flex-wrap items-center gap-3 py-3"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <Link
                                                        href={resumes.show(resume.id)}
                                                        className="truncate font-medium hover:underline"
                                                    >
                                                        {resume.candidate_name ??
                                                            resume.original_filename}
                                                    </Link>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {resume.original_filename} ·{' '}
                                                        {resume.file_size_kb} KB
                                                        {resume.page_count !== null &&
                                                            ` · ${resume.page_count} pages`}
                                                    </p>
                                                    {resume.failure_reason !== null && (
                                                        <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                                                            {resume.failure_reason}
                                                        </p>
                                                    )}
                                                </div>
                                                <ResumeStatusBadge
                                                    status={resume.status}
                                                    label={resume.status_label}
                                                    color={resume.status_color}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        {company.formatting_notes !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>House-style notes</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                                        {company.formatting_notes}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <ResumeUploadCard company={company} />

                        <Card>
                            <CardHeader>
                                <CardTitle>Resume format</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    {company.resume_template_label}
                                    {company.has_custom_section_order &&
                                        ' · custom section order'}
                                </p>
                                <ol className="space-y-1 text-sm">
                                    {company.section_order.map((key, index) => (
                                        <li
                                            key={key}
                                            className="flex items-center gap-2"
                                        >
                                            <span className="w-4 text-xs text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </span>
                                            {SECTION_LABELS[key] ?? key}
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

CompanyShow.layout = {
    breadcrumbs: [{ title: 'Companies', href: companies.index() }],
};