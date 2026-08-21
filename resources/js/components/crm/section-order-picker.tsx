import { ArrowDown, ArrowUp, RotateCcw } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { SectionKey } from '@/types/models';

type Props = {
    template: string;
    /** null = follow the template's default order. */
    value: SectionKey[] | null;
    onChange: (value: SectionKey[] | null) => void;
    error?: string;
};

const LABELS: Record<SectionKey, string> = {
    details: 'Personal details',
    summary: 'Summary',
    experience: 'Experience',
    education: 'Education',
    skills: 'Skills',
    certifications: 'Certifications',
    languages: 'Languages',
};

/** Mirrors ResumeTemplate::defaultSectionOrder() in PHP. */
const TEMPLATE_DEFAULTS: Record<string, SectionKey[]> = {
    classic: [
        'summary',
        'experience',
        'education',
        'skills',
        'certifications',
        'languages',
    ],
    modern: [
        'summary',
        'skills',
        'experience',
        'certifications',
        'education',
        'languages',
    ],
    compact: ['summary', 'skills', 'experience', 'education'],
    professional: [
        'details',
        'summary',
        'skills',
        'experience',
        'certifications',
        'education',
        'languages',
    ],
};

export function SectionOrderPicker({
    template,
    value,
    onChange,
    error,
}: Props) {
    const order =
        value ?? TEMPLATE_DEFAULTS[template] ?? TEMPLATE_DEFAULTS.classic;
    const isCustom = value !== null;

    function move(index: number, direction: -1 | 1) {
        const next = [...order];
        const target = index + direction;

        if (target < 0 || target >= next.length) {
            return;
        }

        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    }

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <Label>Section order</Label>
                {isCustom && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(null)}
                    >
                        <RotateCcw className="size-3.5" aria-hidden />
                        Use template default
                    </Button>
                )}
            </div>

            <ol className="divide-y rounded-md border">
                {order.map((key, index) => (
                    <li
                        key={key}
                        className="flex items-center gap-2 px-3 py-2 text-sm"
                    >
                        <span className="w-5 text-muted-foreground tabular-nums">
                            {index + 1}
                        </span>
                        <span className="flex-1">{LABELS[key]}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            disabled={index === 0}
                            aria-label={`Move ${LABELS[key]} up`}
                            onClick={() => move(index, -1)}
                        >
                            <ArrowUp className="size-3.5" aria-hidden />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            disabled={index === order.length - 1}
                            aria-label={`Move ${LABELS[key]} down`}
                            onClick={() => move(index, 1)}
                        >
                            <ArrowDown className="size-3.5" aria-hidden />
                        </Button>
                    </li>
                ))}
            </ol>

            <p className="text-xs text-muted-foreground">
                {isCustom
                    ? 'Custom order — overrides the template default.'
                    : 'Following the template default. Reorder to customise.'}
            </p>
            <InputError message={error} />
        </div>
    );
}
