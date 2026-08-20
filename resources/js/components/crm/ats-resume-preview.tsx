import { cn } from '@/lib/utils';
import type { AtsDocument, AtsSection, CompanyCard } from '@/types/models';

type Props = {
    document: AtsDocument;
    company: CompanyCard;
};

/**
 * Renders the ATS document the server already laid out. This component makes no
 * decisions about section order or content — that is AtsResumeFormatter's job
 * (DESIGN §1.4). The template only changes typography and density.
 */
export function AtsResumePreview({ document, company }: Props) {
    const { template } = document;
    const compact = template === 'compact';

    return (
        <article
            className={cn(
                'mx-auto w-full max-w-3xl bg-white p-8 text-neutral-900 shadow-sm print:shadow-none dark:bg-neutral-50',
                compact && 'p-6 text-[13px] leading-snug',
            )}
        >
            <header
                className={cn(
                    'border-b pb-4',
                    template === 'modern' && 'border-b-2',
                )}
                style={
                    template === 'modern'
                        ? { borderColor: company.brand_color }
                        : undefined
                }
            >
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2
                            className={cn(
                                'font-semibold tracking-tight',
                                compact ? 'text-xl' : 'text-2xl',
                            )}
                            style={
                                template === 'modern'
                                    ? { color: company.brand_color }
                                    : undefined
                            }
                        >
                            {document.header.name}
                        </h2>
                        <p className="mt-1 text-sm text-neutral-600">
                            {document.header.contact_lines.join(' · ')}
                        </p>
                    </div>
                    {company.logo_url !== null && (
                        <img
                            src={company.logo_url}
                            alt=""
                            width={40}
                            height={40}
                            className="size-10 object-contain opacity-80"
                        />
                    )}
                </div>
            </header>

            <div className={cn('divide-y', compact ? 'text-[13px]' : 'text-sm')}>
                {document.sections.map((section) => (
                    <Section
                        key={section.key}
                        section={section}
                        brandColor={company.brand_color}
                        template={template}
                    />
                ))}
            </div>
        </article>
    );
}

function Section({
    section,
    brandColor,
    template,
}: {
    section: AtsSection;
    brandColor: string;
    template: AtsDocument['template'];
}) {
    return (
        <section className="py-4">
            <h3
                className="mb-2 text-xs font-semibold tracking-[0.12em] uppercase"
                style={
                    template === 'classic'
                        ? undefined
                        : { color: brandColor }
                }
            >
                {section.label}
            </h3>

            {section.type === 'text' && (
                <p className="text-neutral-700">{section.text}</p>
            )}

            {section.type === 'tags' && (
                <ul className="flex flex-wrap gap-1.5">
                    {section.items.map((item) => (
                        <li
                            key={item}
                            className="rounded border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs"
                        >
                            {item}
                        </li>
                    ))}
                </ul>
            )}

            {section.type === 'list' && (
                <ul className="list-disc space-y-1 ps-5 text-neutral-700 marker:text-neutral-400">
                    {section.items.map((item) => (
                        <li key={item}>{item}</li>
                    ))}
                </ul>
            )}

            {section.type === 'timeline' && (
                <ol className="space-y-3">
                    {section.entries.map((entry, index) => (
                        <li key={`${entry.primary}-${index}`}>
                            <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                                <p className="font-semibold">{entry.primary}</p>
                                {entry.period !== '' && (
                                    <p className="text-xs text-neutral-500 tabular-nums">
                                        {entry.period}
                                    </p>
                                )}
                            </div>
                            {(entry.secondary !== null ||
                                entry.location !== null) && (
                                <p className="text-neutral-600">
                                    {[entry.secondary, entry.location]
                                        .filter(Boolean)
                                        .join(' — ')}
                                </p>
                            )}
                            {entry.highlights.length > 0 && (
                                <ul className="mt-1 list-disc space-y-0.5 ps-5 text-neutral-700 marker:text-neutral-400">
                                    {entry.highlights.map((highlight) => (
                                        <li key={highlight}>{highlight}</li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
