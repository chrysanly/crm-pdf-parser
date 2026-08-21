import { Head } from '@inertiajs/react';
import { ResumeTemplateForm } from '@/components/crm/resume-template-form';
import resumeTemplates from '@/routes/resume-templates';
import type {
    EnumOption,
    ResumeTemplate,
    TemplateLayoutValue,
} from '@/types/models';

type Props = {
    template: ResumeTemplate;
    layouts: EnumOption<TemplateLayoutValue>[];
};

export default function ResumeTemplateEdit({ template, layouts }: Props) {
    const record = template;

    return (
        <>
            <Head title={`Edit ${record.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">{record.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {record.layout_label}
                    </p>
                </div>

                <ResumeTemplateForm layouts={layouts} template={record} />
            </div>
        </>
    );
}

ResumeTemplateEdit.layout = {
    breadcrumbs: [
        { title: 'Templates', href: resumeTemplates.index() },
        { title: 'Edit template', href: resumeTemplates.index() },
    ],
};
