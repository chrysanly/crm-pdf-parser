import { Head } from '@inertiajs/react';
import { ResumeTemplateForm } from '@/components/crm/resume-template-form';
import resumeTemplates from '@/routes/resume-templates';
import type { EnumOption, TemplateLayoutValue } from '@/types/models';

type Props = {
    layouts: EnumOption<TemplateLayoutValue>[];
};

export default function ResumeTemplateCreate({ layouts }: Props) {
    return (
        <>
            <Head title="New template" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">New template</h1>
                    <p className="text-sm text-muted-foreground">
                        A template is a base layout plus the order its sections
                        print in. Companies pick one; every resume uploaded for
                        them is rendered with it.
                    </p>
                </div>

                <ResumeTemplateForm layouts={layouts} />
            </div>
        </>
    );
}

ResumeTemplateCreate.layout = {
    breadcrumbs: [
        { title: 'Templates', href: resumeTemplates.index() },
        { title: 'New template', href: resumeTemplates.create() },
    ],
};
