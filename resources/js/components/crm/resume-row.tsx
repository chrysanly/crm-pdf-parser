import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { ResumeStatusBadge } from '@/components/crm/resume-status-badge';
import companies from '@/routes/companies';
import resumes from '@/routes/resumes';
import type { ResumeCard } from '@/types/models';

type Props = {
    resume: ResumeCard;
    /** Show the failure reason inline — used on the "needs attention" list. */
    showReason?: boolean;
};

function relative(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    const then = new Date(iso).getTime();
    const minutes = Math.round((Date.now() - then) / 60000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    return `${Math.round(hours / 24)}d ago`;
}

/**
 * One resume in a cross-company list: who it is, which client it was filed
 * against, and what state it is in.
 */
export function ResumeRow({ resume, showReason = false }: Props) {
    return (
        <li className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
            <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-muted">
                <FileText className="size-4 text-muted-foreground" aria-hidden />
            </span>

            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <Link
                        href={resumes.show(resume.id)}
                        prefetch
                        className="truncate font-medium hover:underline"
                    >
                        {resume.candidate_name ?? resume.original_filename}
                    </Link>
                    <ResumeStatusBadge
                        status={resume.status}
                        label={resume.status_label}
                        color={resume.status_color}
                    />
                </div>

                <p className="truncate text-xs text-muted-foreground">
                    {resume.company !== undefined && (
                        <>
                            <Link
                                href={companies.show(resume.company.slug)}
                                className="hover:underline"
                            >
                                {resume.company.name}
                            </Link>
                            {' · '}
                        </>
                    )}
                    {relative(resume.uploaded_at)}
                </p>

                {showReason && resume.failure_reason !== null && (
                    <p className="text-xs text-red-600 dark:text-red-400">
                        {resume.failure_reason}
                    </p>
                )}
            </div>
        </li>
    );
}
