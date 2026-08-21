import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { CompanyForm } from '@/components/crm/company-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import companies from '@/routes/companies';
import type {
    Company,
    EnumOption,
    LogoPlacementValue,
    LogoSizeValue,
    TemplateOption,
} from '@/types/models';

type Props = {
    company: Company;
    templates: TemplateOption[];
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

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="outline"
                                className="text-destructive"
                            >
                                <Trash2 className="size-4" aria-hidden />
                                Archive company
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                {/* Destructive copy names the object (DESIGN §4). */}
                                <DialogTitle>
                                    Archive “{company.name}”?
                                </DialogTitle>
                                <DialogDescription>
                                    It disappears from the company list and
                                    stops accepting uploads. Existing resumes
                                    and their ATS output stay available for
                                    audit.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="outline">Cancel</Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        router.delete(
                                            companies.destroy.url(company.slug),
                                        )
                                    }
                                >
                                    Archive company
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
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
