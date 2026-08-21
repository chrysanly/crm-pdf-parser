import { useForm } from '@inertiajs/react';
import { ImageUp, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
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
import companies from '@/routes/companies';
import type {
    Company,
    EnumOption,
    LogoPlacementValue,
    LogoSizeValue,
    SectionKey,
    TemplateOption,
} from '@/types/models';
import { CompanyLogo } from './company-logo';
import { SectionOrderPicker } from './section-order-picker';

type Props = {
    templates: TemplateOption[];
    logoPlacements: EnumOption<LogoPlacementValue>[];
    logoSizes: EnumOption<LogoSizeValue>[];
    /** Absent = create mode. */
    company?: Company;
};

type FormValues = {
    name: string;
    industry: string;
    contact_email: string;
    contact_phone: string;
    website: string;
    brand_color: string;
    resume_template: string;
    section_order: SectionKey[] | null;
    formatting_notes: string;
    is_active: boolean;
    logo: File | null;
    remove_logo: boolean;
    logo_placement: LogoPlacementValue;
    logo_size: LogoSizeValue;
};

export function CompanyForm({
    templates,
    logoPlacements,
    logoSizes,
    company,
}: Props) {
    const isEdit = company !== undefined;

    const form = useForm<FormValues>({
        name: company?.name ?? '',
        industry: company?.industry ?? '',
        contact_email: company?.contact_email ?? '',
        contact_phone: company?.contact_phone ?? '',
        website: company?.website ?? '',
        brand_color: company?.brand_color ?? '#1F2937',
        resume_template: company?.resume_template ?? 'classic',
        section_order: company?.has_custom_section_order
            ? company.section_order
            : null,
        formatting_notes: company?.formatting_notes ?? '',
        is_active: company?.is_active ?? true,
        logo: null,
        remove_logo: false,
        logo_placement: company?.logo_placement ?? 'right',
        logo_size: company?.logo_size ?? 'medium',
    });

    const { data, setData, processing, errors, progress } = form;
    const [preview, setPreview] = useState<string | null>(null);

    const logoUrl = useMemo(() => {
        if (preview !== null) {
            return preview;
        }

        if (data.remove_logo) {
            return null;
        }

        return company?.logo_url ?? null;
    }, [preview, data.remove_logo, company?.logo_url]);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        // The logo forces a multipart request, which cannot be a real PUT — so the
        // update is method-spoofed instead.
        form.transform((values) => ({
            ...values,
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        form.post(
            isEdit ? companies.update.url(company.slug) : companies.store.url(),
            { forceFormData: true, preserveScroll: true },
        );
    }

    function pickLogo(file: File | null) {
        setData('logo', file);
        setData('remove_logo', false);
        setPreview(file === null ? null : URL.createObjectURL(file));
    }

    return (
        <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
            <Card className="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Company details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-5 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="name">Company name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            required
                            autoFocus
                            aria-describedby={
                                errors.name ? 'name-error' : undefined
                            }
                        />
                        <InputError id="name-error" message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="industry">Industry</Label>
                        <Input
                            id="industry"
                            value={data.industry}
                            onChange={(event) =>
                                setData('industry', event.target.value)
                            }
                            placeholder="Logistics"
                        />
                        <InputError message={errors.industry} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="website">Website</Label>
                        <Input
                            id="website"
                            type="url"
                            value={data.website}
                            onChange={(event) =>
                                setData('website', event.target.value)
                            }
                            placeholder="https://example.ae"
                        />
                        <InputError message={errors.website} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="contact_email">Contact email</Label>
                        <Input
                            id="contact_email"
                            type="email"
                            value={data.contact_email}
                            onChange={(event) =>
                                setData('contact_email', event.target.value)
                            }
                            placeholder="talent@example.ae"
                        />
                        <InputError message={errors.contact_email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="contact_phone">Contact phone</Label>
                        <Input
                            id="contact_phone"
                            value={data.contact_phone}
                            onChange={(event) =>
                                setData('contact_phone', event.target.value)
                            }
                            placeholder="+971501234567"
                            inputMode="tel"
                        />
                        <InputError message={errors.contact_phone} />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="formatting_notes">
                            House-style notes
                            <span className="ms-2 text-xs font-normal text-muted-foreground">
                                shown next to every ATS preview
                            </span>
                        </Label>
                        <textarea
                            id="formatting_notes"
                            value={data.formatting_notes}
                            onChange={(event) =>
                                setData('formatting_notes', event.target.value)
                            }
                            rows={3}
                            className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Reverse-chronological only. Dates as “Mon YYYY”. Max four bullets per role."
                        />
                        <InputError message={errors.formatting_notes} />
                    </div>

                    <div className="flex items-center gap-3 sm:col-span-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="is_active" className="font-normal">
                            Active — resumes can be uploaded for this company
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Brand</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-2">
                            <Label>Logo</Label>
                            <div className="flex items-center gap-3">
                                <CompanyLogo
                                    name={data.name || 'New company'}
                                    logoUrl={logoUrl}
                                    brandColor={data.brand_color}
                                />
                                <div className="flex flex-col gap-2">
                                    <Label
                                        htmlFor="logo"
                                        className="inline-flex w-fit cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-accent"
                                    >
                                        <ImageUp
                                            className="size-4"
                                            aria-hidden
                                        />
                                        Choose image
                                    </Label>
                                    <input
                                        id="logo"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        className="sr-only"
                                        onChange={(event) =>
                                            pickLogo(
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                    {logoUrl !== null && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="w-fit text-destructive"
                                            onClick={() => {
                                                setPreview(null);
                                                setData('logo', null);
                                                setData('remove_logo', true);
                                            }}
                                        >
                                            <Trash2
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Remove
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                PNG, JPG or WebP · min 64×64 · max 2 MB. Images
                                are re-encoded on upload.
                            </p>
                            <InputError message={errors.logo} />
                        </div>

                        {/* Letterhead: saved per company and applied to every
                            resume preview and printout for that client. */}
                        <div className="grid gap-2">
                            <Label htmlFor="logo_placement">
                                Logo on the resume
                            </Label>
                            <Select
                                value={data.logo_placement}
                                onValueChange={(value) =>
                                    setData(
                                        'logo_placement',
                                        value as LogoPlacementValue,
                                    )
                                }
                            >
                                <SelectTrigger id="logo_placement">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {logoPlacements.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.logo_placement} />
                        </div>

                        {data.logo_placement !== 'hidden' && (
                            <div className="grid gap-2">
                                <Label htmlFor="logo_size">Logo size</Label>
                                <div
                                    className="flex gap-2"
                                    role="group"
                                    aria-labelledby="logo_size"
                                >
                                    {logoSizes.map((option) => (
                                        <Button
                                            key={option.value}
                                            type="button"
                                            variant={
                                                data.logo_size === option.value
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            aria-pressed={
                                                data.logo_size === option.value
                                            }
                                            onClick={() =>
                                                setData(
                                                    'logo_size',
                                                    option.value,
                                                )
                                            }
                                        >
                                            {option.label}
                                        </Button>
                                    ))}
                                </div>
                                <InputError message={errors.logo_size} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="brand_color">Brand colour</Label>
                            <div className="flex items-center gap-2">
                                <input
                                    id="brand_color"
                                    type="color"
                                    value={data.brand_color}
                                    onChange={(event) =>
                                        setData(
                                            'brand_color',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    className="size-9 cursor-pointer rounded-md border bg-transparent"
                                />
                                <Input
                                    value={data.brand_color}
                                    onChange={(event) =>
                                        setData(
                                            'brand_color',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    className="font-mono"
                                    maxLength={7}
                                />
                            </div>
                            <InputError message={errors.brand_color} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Resume format</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="resume_template">Template</Label>
                            <Select
                                value={data.resume_template}
                                onValueChange={(value) => {
                                    setData('resume_template', value);
                                    setData('section_order', null);
                                }}
                            >
                                <SelectTrigger id="resume_template">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {templates.map((template) => (
                                        <SelectItem
                                            key={template.value}
                                            value={template.value}
                                        >
                                            {template.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.resume_template} />
                        </div>

                        <SectionOrderPicker
                            template={data.resume_template}
                            value={data.section_order}
                            onChange={(value) =>
                                setData('section_order', value)
                            }
                            error={errors.section_order}
                        />
                    </CardContent>
                </Card>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {isEdit ? 'Save company' : 'Create company'}
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
