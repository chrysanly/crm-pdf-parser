import { Link } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import resumeTemplates from '@/routes/resume-templates';
import type { ResumeTemplateCard, SectionKey } from '@/types/models';

type Props = {
    /** null while no template is selected, or none exists yet. */
    template: ResumeTemplateCard | null;
};

const SECTION_LABELS: Record<SectionKey, string> = {
    details: 'Personal details',
    summary: 'Summary',
    experience: 'Experience',
    education: 'Education',
    skills: 'Skills',
    certifications: 'Certifications',
    languages: 'Languages',
};

/**
 * Read-only view of what the chosen template will print. The order itself is
 * edited on the template, never per company — one house style, one definition.
 */
export function TemplateSummary({ template }: Props) {
    if (template === null) {
        return (
            <p className="rounded-md border border-dashed px-3 py-4 text-sm text-muted-foreground">
                No template selected yet.{' '}
                <Link
                    href={resumeTemplates.create.url()}
                    className="font-medium underline underline-offset-4"
                >
                    Create one
                </Link>{' '}
                to define the section order.
            </p>
        );
    }

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-2">
                <span className="text-sm font-medium">Section order</span>
                <Link
                    href={resumeTemplates.edit.url(template.slug)}
                    className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                >
                    <SlidersHorizontal className="size-3.5" aria-hidden />
                    Edit template
                </Link>
            </div>

            <ol className="divide-y rounded-md border">
                {template.section_order.map((key, index) => (
                    <li
                        key={key}
                        className="flex items-center gap-2 px-3 py-1.5 text-sm"
                    >
                        <span className="w-5 text-muted-foreground tabular-nums">
                            {index + 1}
                        </span>
                        <span>{SECTION_LABELS[key]}</span>
                    </li>
                ))}
            </ol>

            <p className="text-xs text-muted-foreground">
                {template.layout_label}
            </p>
        </div>
    );
}
