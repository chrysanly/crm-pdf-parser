import { cn } from '@/lib/utils';
import type { AtsDocument, AtsSection, CompanyCard } from '@/types/models';

type Props = {
    document: AtsDocument;
    company: CompanyCard;
};

/**
 * Renders the ATS document the server already laid out. This component makes no
 * decisions about section order or content — that is AtsResumeFormatter's job
 * (DESIGN §1.4). The template decides typography, density and header shape.
 *
 * The `professional` template mirrors a conventional print resume: centred name
 * with a job-title line, and a ruled heading above every section.
 */
export function AtsResumePreview({ document, company }: Props) {
    const { template, header } = document;
    const compact = template === 'compact';
    const ruled = template === 'professional';
    const logo = header.logo;

    const logoImage =
        logo === null || company.logo_url === null ? null : (
            <img
                src={company.logo_url}
                alt={`${company.name} logo`}
                width={logo.pixels}
                height={logo.pixels}
                style={{ width: logo.pixels, height: logo.pixels }}
                className="shrink-0 object-contain print:!block"
            />
        );

    return (
        <article
            className={cn(
                'mx-auto w-full max-w-3xl bg-white p-8 text-neutral-900 shadow-sm dark:bg-neutral-50 print:shadow-none',
                compact && 'p-6 text-[13px] leading-snug',
                ruled && 'text-[13.5px] leading-relaxed',
            )}
        >
            <Header
                document={document}
                company={company}
                logoImage={logoImage}
                ruled={ruled}
                compact={compact}
            />

            <div
                className={cn(
                    ruled ? 'space-y-4' : 'divide-y',
                    compact ? 'text-[13px]' : 'text-sm',
                )}
            >
                {document.sections.map((section) => (
                    <Section
                        key={section.key}
                        section={section}
                        brandColor={company.brand_color}
                        template={template}
                        ruled={ruled}
                    />
                ))}
            </div>
        </article>
    );
}

function Header({
    document,
    company,
    logoImage,
    ruled,
    compact,
}: {
    document: AtsDocument;
    company: CompanyCard;
    logoImage: React.ReactNode;
    ruled: boolean;
    compact: boolean;
}) {
    const { header, template } = document;
    const placement = header.logo?.placement ?? 'right';

    const nameBlock = (
        <div className={cn(header.centred && 'text-center')}>
            <h2
                className={cn(
                    'font-bold tracking-tight',
                    compact ? 'text-xl' : 'text-2xl',
                )}
                style={
                    template === 'modern'
                        ? { color: company.brand_color }
                        : undefined
                }
            >
                {header.name}
            </h2>
            {header.headline !== null && (
                <p className="mt-0.5 text-base text-neutral-700">
                    {header.headline}
                </p>
            )}
            {header.contact_lines.length > 0 && (
                <p className="mt-1 text-sm text-neutral-600">
                    {header.contact_lines.join(' · ')}
                </p>
            )}
        </div>
    );

    // Centre placement stacks the logo above the name; left/right sit beside it.
    if (placement === 'centre' && logoImage !== null) {
        return (
            <header className={cn('pb-4', !ruled && 'border-b')}>
                <div className="flex flex-col items-center gap-2">
                    {logoImage}
                    {nameBlock}
                </div>
            </header>
        );
    }

    return (
        <header
            className={cn(
                'pb-4',
                !ruled && 'border-b',
                template === 'modern' && 'border-b-2',
            )}
            style={
                template === 'modern'
                    ? { borderColor: company.brand_color }
                    : undefined
            }
        >
            <div
                className={cn(
                    'flex items-start gap-4',
                    header.centred ? 'justify-center' : 'justify-between',
                    placement === 'left' && 'flex-row-reverse justify-end',
                )}
            >
                {header.centred && placement === 'right' ? (
                    <>
                        <span
                            aria-hidden
                            style={{ width: header.logo?.pixels ?? 0 }}
                            className="shrink-0"
                        />
                        {nameBlock}
                        {logoImage}
                    </>
                ) : (
                    <>
                        {nameBlock}
                        {logoImage}
                    </>
                )}
            </div>
        </header>
    );
}

function Section({
    section,
    brandColor,
    template,
    ruled,
}: {
    section: AtsSection;
    brandColor: string;
    template: AtsDocument['template'];
    ruled: boolean;
}) {
    return (
        <section className={ruled ? '' : 'py-4'}>
            <h3
                className={cn(
                    'text-xs font-bold tracking-[0.08em] uppercase',
                    // A rule under the heading, the way the reference document does it.
                    ruled
                        ? 'mb-2 border-b border-neutral-400 pb-1'
                        : 'mb-2 tracking-[0.12em]',
                )}
                style={
                    template === 'classic' || ruled
                        ? undefined
                        : { color: brandColor }
                }
            >
                {section.label}
            </h3>

            {section.type === 'text' && (
                <p className="text-neutral-700">{section.text}</p>
            )}

            {section.type === 'details' && (
                <dl className="grid gap-x-6 gap-y-0.5 sm:grid-cols-[auto_1fr]">
                    {section.rows.map((row) => (
                        <div key={row.label} className="sm:contents">
                            <dt className="font-medium text-neutral-800">
                                {row.label}:
                            </dt>
                            <dd className="text-neutral-700">{row.value}</dd>
                        </div>
                    ))}
                </dl>
            )}

            {section.type === 'skill_groups' && (
                <ul className="space-y-1">
                    {section.groups.map((group, index) => (
                        <li key={group.label ?? `group-${index}`}>
                            {group.label !== null && (
                                <span className="font-semibold text-neutral-800">
                                    {group.label}:{' '}
                                </span>
                            )}
                            <span className="text-neutral-700">
                                {group.items.join(', ')}
                            </span>
                        </li>
                    ))}
                </ul>
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
                <ol className={ruled ? 'space-y-3.5' : 'space-y-3'}>
                    {section.entries.map((entry, index) => (
                        <li key={`${entry.primary}-${index}`}>
                            {ruled ? (
                                // Reference layout: bold role - employer - location on
                                // one line, dates on the line below.
                                <>
                                    <p className="font-bold">
                                        {entry.primary}
                                        {entry.secondary !== null && (
                                            <span className="font-semibold text-neutral-700">
                                                {' '}
                                                - {entry.secondary}
                                            </span>
                                        )}
                                        {entry.location !== null && (
                                            <span className="font-semibold text-neutral-700">
                                                {' '}
                                                - {entry.location}
                                            </span>
                                        )}
                                    </p>
                                    {entry.period !== '' && (
                                        <p className="text-neutral-600">
                                            {entry.period}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <>
                                    <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <p className="font-semibold">
                                            {entry.primary}
                                        </p>
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
                                </>
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
