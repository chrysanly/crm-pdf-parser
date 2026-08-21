import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    FileText,
    LayoutTemplate,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDestructiveDialog } from '@/components/crm/confirm-destructive-dialog';
import { EmptyState } from '@/components/crm/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import resumeTemplates from '@/routes/resume-templates';
import type { Paginated, ResumeTemplateCard } from '@/types/models';

type Props = {
    templates: Paginated<ResumeTemplateCard>;
    filters: { search: string | null };
};

/** Laravel ships pagination labels with HTML entities; render them as text. */
function paginationLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}

export default function ResumeTemplatesIndex({
    templates: page,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applySearch(event: React.FormEvent) {
        event.preventDefault();

        router.get(
            resumeTemplates.index.url(),
            { search: search === '' ? undefined : search },
            {
                only: ['templates', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title="Templates" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Templates</h1>
                        <p className="text-sm text-muted-foreground">
                            {page.meta.total} house style
                            {page.meta.total === 1 ? '' : 's'} · a layout plus
                            the order its sections print in
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <form onSubmit={applySearch} className="relative">
                            <Search
                                className="pointer-events-none absolute start-2.5 top-2.5 size-4 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search templates"
                                aria-label="Search templates"
                                className="ps-8 sm:w-64"
                            />
                        </form>
                        <Button asChild>
                            <Link href={resumeTemplates.create()}>
                                <Plus className="size-4" aria-hidden />
                                New template
                            </Link>
                        </Button>
                    </div>
                </div>

                {page.data.length === 0 ? (
                    <EmptyState
                        icon={LayoutTemplate}
                        title={
                            filters.search === null
                                ? 'No templates yet'
                                : `Nothing matches “${filters.search}”`
                        }
                        description={
                            filters.search === null
                                ? 'Create a template to define a house style, then assign it to the companies that use it.'
                                : 'Try a different name, or create the template now.'
                        }
                        action={
                            <Button asChild>
                                <Link href={resumeTemplates.create()}>
                                    <Plus className="size-4" aria-hidden />
                                    New template
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {page.data.map((template) => (
                            <Card key={template.id}>
                                <CardContent className="grid gap-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <Link
                                            href={resumeTemplates.edit(
                                                template.slug,
                                            )}
                                            prefetch
                                            className="font-medium hover:underline"
                                        >
                                            {template.name}
                                        </Link>
                                        {!template.is_active && (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>

                                    <p className="text-sm text-muted-foreground">
                                        {template.description ??
                                            template.layout_label}
                                    </p>

                                    <ol className="flex flex-wrap gap-1">
                                        {template.section_order.map(
                                            (key, index) => (
                                                <li key={key}>
                                                    <Badge
                                                        variant="outline"
                                                        className="font-normal"
                                                    >
                                                        {index + 1}. {key}
                                                    </Badge>
                                                </li>
                                            ),
                                        )}
                                    </ol>

                                    <div className="flex items-center justify-between gap-2 border-t pt-3">
                                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                            <span className="inline-flex items-center gap-1">
                                                <Building2
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                {template.companies_count ?? 0}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <FileText
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                {template.resumes_count ?? 0}
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={resumeTemplates.edit(
                                                        template.slug,
                                                    )}
                                                >
                                                    <Pencil
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    Edit
                                                </Link>
                                            </Button>

                                            <ConfirmDestructiveDialog
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive"
                                                        disabled={
                                                            (template.companies_count ??
                                                                0) > 0
                                                        }
                                                        title={
                                                            (template.companies_count ??
                                                                0) > 0
                                                                ? 'Reassign the companies using this template first'
                                                                : undefined
                                                        }
                                                    >
                                                        <Trash2
                                                            className="size-3.5"
                                                            aria-hidden
                                                        />
                                                        Archive
                                                    </Button>
                                                }
                                                title={`Archive “${template.name}”?`}
                                                description="It disappears from the template picker. Resumes already produced with it keep rendering exactly as they do now."
                                                confirmLabel="Archive template"
                                                pendingLabel="Archiving…"
                                                url={resumeTemplates.destroy.url(
                                                    template.slug,
                                                )}
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {page.meta.last_page > 1 && (
                    <nav
                        className="flex flex-wrap items-center gap-1"
                        aria-label="Pagination"
                    >
                        {page.links.map((link) => (
                            <Button
                                key={link.label}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={link.url === null}
                                onClick={() =>
                                    link.url !== null &&
                                    router.get(
                                        link.url,
                                        {},
                                        {
                                            only: ['templates'],
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
                            >
                                {paginationLabel(link.label)}
                            </Button>
                        ))}
                    </nav>
                )}
            </div>
        </>
    );
}

ResumeTemplatesIndex.layout = {
    breadcrumbs: [{ title: 'Templates', href: resumeTemplates.index() }],
};
