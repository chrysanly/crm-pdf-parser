import { Head } from '@inertiajs/react';
import { CompanyForm } from '@/components/crm/company-form';
import companies from '@/routes/companies';
import type {
    EnumOption,
    LogoPlacementValue,
    LogoSizeValue,
    ResumeTemplateCard,
} from '@/types/models';

type Props = {
    templates: ResumeTemplateCard[];
    logoPlacements: EnumOption<LogoPlacementValue>[];
    logoSizes: EnumOption<LogoSizeValue>[];
};

export default function CompanyCreate({
    templates,
    logoPlacements,
    logoSizes,
}: Props) {
    return (
        <>
            <Head title="Add company" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Add company</h1>
                    <p className="text-sm text-muted-foreground">
                        The template and section order you choose here decide
                        how every resume uploaded for this company gets
                        reformatted.
                    </p>
                </div>

                <CompanyForm
                    templates={templates}
                    logoPlacements={logoPlacements}
                    logoSizes={logoSizes}
                />
            </div>
        </>
    );
}

CompanyCreate.layout = {
    breadcrumbs: [
        { title: 'Companies', href: companies.index() },
        { title: 'Add company', href: companies.create() },
    ],
};
