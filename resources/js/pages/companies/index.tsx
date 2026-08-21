import { Head, Link, router } from '@inertiajs/react';
import { Building2, FileText, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { CompanyLogo } from '@/components/crm/company-logo';
import { EmptyState } from '@/components/crm/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import companies from '@/routes/companies';
import type { CompanyCard, Paginated } from '@/types/models';

type Props = {
    companies: Paginated<CompanyCard>;
    filters: { search: string | null };
};

/** Laravel ships pagination labels with HTML entities; render them as text. */
function paginationLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}

export default function CompaniesIndex({ companies: page, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applySearch(event: React.FormEvent) {
        event.preventDefault();

        // Partial reload: only the list comes back, the URL stays shareable
        // (ARCHITECTURE §8).
        router.get(
            companies.index.url(),
            { search: search === '' ? undefined : search },
            {
                only: ['companies', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return (
        <>
            <Head title="Companies" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Companies</h1>
                        <p className="text-sm text-muted-foreground">
                            {page.meta.total} client
                            {page.meta.total === 1 ? '' : 's'} · each one
                            defines its own resume format
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
                                placeholder="Search name or industry"
                                aria-label="Search companies"
                                className="ps-8 sm:w-64"
                            />
                        </form>
                        <Button asChild>
                            <Link href={companies.create()}>
                                <Plus className="size-4" aria-hidden />
                                Add company
                            </Link>
                        </Button>
                    </div>
                </div>

                {page.data.length === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title={
                            filters.search === null
                                ? 'No companies yet'
                                : `Nothing matches “${filters.search}”`
                        }
                        description={
                            filters.search === null
                                ? 'Add your first client company, then upload a candidate resume to see it reformatted in that company’s house style.'
                                : 'Try a different name or industry, or add this company now.'
                        }
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
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {page.data.map((company) => (
                            <Card
                                key={company.id}
                                className="transition-colors hover:border-primary/40"
                            >
                                <CardContent className="flex items-start gap-4">
                                    <CompanyLogo
                                        name={company.name}
                                        logoUrl={company.logo_url}
                                        brandColor={company.brand_color}
                                    />
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <Link
                                                href={companies.show(
                                                    company.slug,
                                                )}
                                                prefetch
                                                className="truncate font-medium hover:underline"
                                            >
                                                {company.name}
                                            </Link>
                                            {!company.is_active && (
                                                <Badge variant="secondary">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {company.industry ??
                                                'No industry set'}
                                        </p>
                                        <div className="flex flex-wrap items-center gap-2 pt-1">
                                            <Badge variant="outline">
                                                {company.resume_template_name}
                                            </Badge>
                                            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                <FileText
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                {company.resumes_count ?? 0}{' '}
                                                resume
                                                {company.resumes_count === 1
                                                    ? ''
                                                    : 's'}
                                            </span>
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
                                            only: ['companies'],
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

CompaniesIndex.layout = {
    breadcrumbs: [{ title: 'Companies', href: companies.index() }],
};
