import { useForm } from '@inertiajs/react';
import { FileUp, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import companies from '@/routes/companies';
import type { Company } from '@/types/models';

type Props = {
    company: Company;
};

/**
 * Upload a candidate PDF for this company. The submit button is disabled while
 * processing — that plus the server-side file hash is the double-submit guard
 * (DESIGN §4, RULES §5.5).
 */
export function ResumeUploadCard({ company }: Props) {
    const form = useForm<{ file: File | null }>({ file: null });
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const { data, setData, processing, errors, progress } = form;

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(companies.resumes.store.url(company.slug), {
            forceFormData: true,
            preserveScroll: true,
        });
    }

    function accept(file: File | undefined) {
        if (file === undefined) {
            return;
        }

        setData('file', file);
    }

    if (!company.is_active) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Upload a resume</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        This company is inactive, so new resumes cannot be
                        uploaded. Reactivate it from{' '}
                        <span className="font-medium">Edit company</span> first.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Upload a resume</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={(event) => {
                            event.preventDefault();
                            setDragging(false);
                            accept(event.dataTransfer.files[0]);
                        }}
                        className={cn(
                            'flex flex-col items-center gap-3 rounded-xl border border-dashed p-6 text-center transition-colors',
                            dragging && 'border-primary bg-primary/5',
                        )}
                    >
                        <span className="flex size-11 items-center justify-center rounded-full bg-muted">
                            <FileUp
                                className="size-5 text-muted-foreground"
                                aria-hidden
                            />
                        </span>

                        <div className="space-y-1">
                            <p className="text-sm font-medium">
                                {data.file?.name ?? 'Drop a PDF resume here'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                PDF only · max 10 MB · parsed into{' '}
                                {company.name}’s format automatically
                            </p>
                        </div>

                        <Label
                            htmlFor="resume-file"
                            className="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-accent"
                        >
                            Choose file
                        </Label>
                        <input
                            id="resume-file"
                            ref={inputRef}
                            type="file"
                            accept="application/pdf"
                            className="sr-only"
                            onChange={(event) =>
                                accept(event.target.files?.[0] ?? undefined)
                            }
                        />
                    </div>

                    <InputError message={errors.file} />

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing || data.file === null}
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Upload className="size-4" aria-hidden />
                            )}
                            Upload &amp; parse
                        </Button>
                        {progress !== null && progress !== undefined && (
                            <span
                                className="text-sm text-muted-foreground"
                                role="status"
                                aria-live="polite"
                            >
                                {progress.percentage ?? 0}%
                            </span>
                        )}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
