import { Head, Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { CompanyForm } from '@/components/crm/company-form';
import { ConfirmDestructiveDialog } from '@/components/crm/confirm-destructive-dialog';
import { Button } from '@/components/ui/button';
import companies from '@/routes/companies';
import type {
    Company,
    EnumOption,
    LogoPlacementValue,
    LogoSizeValue,
    ResumeTemplateCard,
} from '@/types/models';

type Props = {
    company: Company;
    templates: ResumeTemplateCard[];
    logoPlacements: EnumOption<LogoPlacementValue>[];
    logoSizes: EnumOption<LogoSizeValue>[];
};

export default function CompanyEdit({
    company,
    templates,
    logoPlacements,
    logoSizes,
}: Props) {
    return (
        <>
            <Head title={`Edit ${company.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Edit {company.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Changes apply to resumes reformatted from now on.
                        </p>
                    </div>

                    <ConfirmDestructiveDialog
                        trigger={
                            <Button
                                variant="outline"
                                className="text-destructive"
                            >
                                <Trash2 className="size-4" aria-hidden />
                                Archive company
                            </Button>
                        }
                        title={`Archive “${company.name}”?`}
                        description="It disappears from the company list and stops accepting uploads. Existing resumes and their ATS output stay available for audit."
                        confirmLabel="Archive company"
                        pendingLabel="Archiving…"
                        url={companies.destroy.url(company.slug)}
                    />
                </div>

                <CompanyForm
                    templates={templates}
                    logoPlacements={logoPlacements}
                    logoSizes={logoSizes}
                    company={company}
                />

                <Button variant="ghost" asChild className="w-fit">
                    <Link href={companies.show(company.slug)}>
                        Back to company
                    </Link>
                </Button>
            </div>
        </>
    );
}

CompanyEdit.layout = {
    breadcrumbs: [{ title: 'Companies', href: companies.index() }],
};
