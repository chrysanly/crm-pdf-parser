import { router } from '@inertiajs/react';
import { Check, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import resumes from '@/routes/resumes';
import type { Resume, ResumeTemplateCard } from '@/types/models';

type Props = {
    resume: Resume;
    templates: ResumeTemplateCard[];
    /** Parsing in flight — nothing may be re-styled or re-read right now. */
    busy: boolean;
};

/**
 * Re-style this document, or send it back through the parser.
 *
 * Applying a template is presentation only: the parsed data is untouched, so it
 * is instant. Re-parsing re-reads the source PDF and is queued, which is why the
 * two are separate buttons rather than one "save".
 */
export function ResumeTemplateSwitcher({ resume, templates, busy }: Props) {
    const current = resume.resume_template_slug;
    const [selected, setSelected] = useState(current ?? '');
    const [applying, setApplying] = useState(false);
    const [reparsing, setReparsing] = useState(false);

    const dirty = selected !== '' && selected !== current;
    const working = applying || reparsing || busy;

    function apply() {
        if (!dirty) {
            return;
        }

        setApplying(true);

        router.put(
            resumes.template.url(resume.id),
            { resume_template: selected },
            {
                preserveScroll: true,
                onFinish: () => setApplying(false),
            },
        );
    }

    function reparse() {
        setReparsing(true);

        router.post(
            resumes.reparse.url(resume.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => setReparsing(false),
            },
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Template</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="resume_template">
                        Applied to this document
                    </Label>
                    <Select
                        value={selected}
                        onValueChange={setSelected}
                        disabled={working || templates.length === 0}
                    >
                        <SelectTrigger id="resume_template">
                            <SelectValue placeholder="Choose a template" />
                        </SelectTrigger>
                        <SelectContent>
                            {templates.map((template) => (
                                <SelectItem
                                    key={template.slug}
                                    value={template.slug}
                                >
                                    {template.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        {dirty
                            ? 'Applying re-lays out this resume — the parsed data is untouched.'
                            : 'Frozen when this resume was uploaded. Change it any time.'}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        onClick={apply}
                        disabled={!dirty || working}
                        aria-busy={applying}
                    >
                        {applying ? (
                            <Spinner />
                        ) : (
                            <Check className="size-4" aria-hidden />
                        )}
                        {applying ? 'Applying…' : 'Apply template'}
                    </Button>

                    <Button
                        size="sm"
                        variant="outline"
                        onClick={reparse}
                        disabled={working}
                        aria-busy={reparsing}
                    >
                        {reparsing ? (
                            <Spinner />
                        ) : (
                            <RefreshCw className="size-4" aria-hidden />
                        )}
                        {reparsing ? 'Queueing…' : 'Re-parse PDF'}
                    </Button>
                </div>

                {busy && (
                    <p
                        className="text-xs text-muted-foreground"
                        role="status"
                        aria-live="polite"
                    >
                        Waiting for the parser to finish…
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
