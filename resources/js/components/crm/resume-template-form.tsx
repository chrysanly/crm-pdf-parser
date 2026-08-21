import { router, useForm } from '@inertiajs/react';
import { FileUp, RefreshCw, Trash2, TriangleAlert } from 'lucide-react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import resumeTemplates from '@/routes/resume-templates';
import type {
    EnumOption,
    ResumeTemplate,
    SectionKey,
    TemplateLayoutValue,
} from '@/types/models';
import { SectionOrderPicker } from './section-order-picker';

type Props = {
    layouts: EnumOption<TemplateLayoutValue>[];
    /** Absent = create mode. */
    template?: ResumeTemplate;
};

type FormValues = {
    name: string;
    description: string;
    layout: TemplateLayoutValue;
    /** null = follow the layout's default order. */
    section_order: SectionKey[] | null;
    is_active: boolean;
    /** A sample resume to copy the printed section order from. */
    sample_resume: File | null;
    remove_sample: boolean;
};

export function ResumeTemplateForm({ layouts, template }: Props) {
    const isEdit = template !== undefined;

    const form = useForm<FormValues>({
        name: template?.name ?? '',
        description: template?.description ?? '',
        layout: template?.layout ?? 'classic',
        section_order: template?.has_custom_section_order
            ? template.section_order
            : null,
        is_active: template?.is_active ?? true,
        sample_resume: null,
        remove_sample: false,
    });

    const { data, setData, processing, errors, progress } = form;

    // The sample is read on the queue, so the page polls until it lands and the
    // derived section order appears in the picker.
    const sampleInFlight =
        template?.sample_status === 'pending' ||
        template?.sample_status === 'processing';

    useEffect(() => {
        if (!sampleInFlight) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['template'] });
        }, 2500);

        return () => window.clearInterval(timer);
    }, [sampleInFlight]);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        // A file forces a multipart request, which cannot be a real PUT — so the
        // update is method-spoofed instead.
        form.transform((values) => ({
            ...values,
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(
            isEdit
                ? resumeTemplates.update.url(template.slug)
                : resumeTemplates.store.url(),
            { forceFormData: true, preserveScroll: true },
        );
    }

    return (
        <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Template</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            required
                            autoFocus
                            placeholder="Al Mutakamela house style"
                            aria-describedby={
                                errors.name ? 'name-error' : undefined
                            }
                        />
                        <InputError id="name-error" message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">
                            Description
                            <span className="ms-2 text-xs font-normal text-muted-foreground">
                                optional
                            </span>
                        </Label>
                        <Input
                            id="description"
                            value={data.description}
                            onChange={(event) =>
                                setData('description', event.target.value)
                            }
                            placeholder="Centred header, personal details first."
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="layout">Base layout</Label>
                        <Select
                            value={data.layout}
                            onValueChange={(value) => {
                                setData('layout', value as TemplateLayoutValue);
                                // A new layout brings its own default order.
                                setData('section_order', null);
                            }}
                        >
                            <SelectTrigger id="layout">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {layouts.map((layout) => (
                                    <SelectItem
                                        key={layout.value}
                                        value={layout.value}
                                    >
                                        {layout.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.layout} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="is_active" className="font-normal">
                            Active — offered when choosing a company's template
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Section order</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <SectionOrderPicker
                            layout={data.layout}
                            value={data.section_order}
                            onChange={(value) =>
                                setData('section_order', value)
                            }
                            error={errors.section_order}
                        />

                        {/* Build the order from a document instead of by hand:
                            the sample is parsed and the sections it printed —
                            in its order — become this template's order. */}
                        <div className="grid gap-2 border-t pt-4">
                            <Label htmlFor="sample_resume">
                                Copy the order from a sample resume
                                <span className="ms-2 text-xs font-normal text-muted-foreground">
                                    optional
                                </span>
                            </Label>

                            <div className="flex flex-wrap items-center gap-2">
                                <Label
                                    htmlFor="sample_resume"
                                    className="inline-flex w-fit cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-accent"
                                >
                                    <FileUp className="size-4" aria-hidden />
                                    {data.sample_resume === null
                                        ? 'Choose a PDF'
                                        : 'Change PDF'}
                                </Label>
                                <input
                                    id="sample_resume"
                                    type="file"
                                    accept="application/pdf"
                                    className="sr-only"
                                    onChange={(event) => {
                                        setData(
                                            'sample_resume',
                                            event.target.files?.[0] ?? null,
                                        );
                                        setData('remove_sample', false);
                                    }}
                                />

                                {data.sample_resume !== null && (
                                    <span className="text-sm text-muted-foreground">
                                        {data.sample_resume.name}
                                    </span>
                                )}
                            </div>

                            {isEdit &&
                                template.sample_filename !== null &&
                                data.sample_resume === null &&
                                !data.remove_sample && (
                                    <div className="flex flex-wrap items-center gap-2 text-sm">
                                        <span className="break-all text-muted-foreground">
                                            {template.sample_filename}
                                        </span>
                                        {sampleInFlight ? (
                                            <Badge
                                                variant="secondary"
                                                className="gap-1"
                                            >
                                                <RefreshCw
                                                    className="size-3 animate-spin"
                                                    aria-hidden
                                                />
                                                Reading…
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                {template.sample_status_label ??
                                                    'Stored'}
                                            </Badge>
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-destructive"
                                            onClick={() =>
                                                setData('remove_sample', true)
                                            }
                                        >
                                            <Trash2
                                                className="size-3.5"
                                                aria-hidden
                                            />
                                            Remove
                                        </Button>
                                    </div>
                                )}

                            {isEdit && data.remove_sample && (
                                <p className="text-xs text-muted-foreground">
                                    The stored sample will be deleted when you
                                    save.
                                </p>
                            )}

                            {isEdit &&
                                template.sample_failure_reason !== null &&
                                !sampleInFlight && (
                                    <p className="flex items-start gap-1.5 text-xs text-destructive">
                                        <TriangleAlert
                                            className="mt-0.5 size-3.5 shrink-0"
                                            aria-hidden
                                        />
                                        {template.sample_failure_reason}
                                    </p>
                                )}

                            <p className="text-xs text-muted-foreground">
                                PDF up to 10 MB. Only the section order is taken
                                from it — the look comes from the base layout.
                                The file is stored privately and never shown.
                            </p>
                            <InputError message={errors.sample_resume} />
                        </div>
                    </CardContent>
                </Card>

                {isEdit && (template.companies_count ?? 0) > 0 && (
                    <p className="text-sm text-muted-foreground">
                        {template.companies_count} company
                        {template.companies_count === 1 ? '' : 's'} use this
                        template. Saving changes how their <em>future</em>{' '}
                        uploads are rendered; resumes already produced keep the
                        style they were uploaded with.
                    </p>
                )}

                <div className="flex items-center gap-3">
                    <Button
                        type="submit"
                        disabled={processing}
                        aria-busy={processing}
                    >
                        {processing && <Spinner />}
                        {processing
                            ? 'Saving…'
                            : isEdit
                              ? 'Save template'
                              : 'Create template'}
                    </Button>
                    {progress !== null && progress !== undefined && (
                        <span
                            className="text-sm text-muted-foreground"
                            role="status"
                            aria-live="polite"
                        >
                            Uploading {progress.percentage ?? 0}%
                        </span>
                    )}
                </div>
            </div>
        </form>
    );
}
