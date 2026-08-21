import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    value: number | string;
    /** One line of context under the number — what it is measured against. */
    hint?: string;
    icon: LucideIcon;
    /** `attention` is for a figure that means someone has work to do. */
    tone?: 'default' | 'attention' | 'positive';
};

const TONE = {
    default: 'text-muted-foreground',
    attention: 'text-red-600 dark:text-red-400',
    positive: 'text-emerald-600 dark:text-emerald-400',
} as const;

/** One number on the dashboard, with the context that makes it mean something. */
export function StatTile({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'default',
}: Props) {
    return (
        <Card>
            <CardContent className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p
                        className={cn(
                            'text-2xl font-semibold tabular-nums',
                            tone === 'attention' && TONE.attention,
                        )}
                    >
                        {value}
                    </p>
                    {hint !== undefined && (
                        <p className="truncate text-xs text-muted-foreground">
                            {hint}
                        </p>
                    )}
                </div>
                <Icon
                    className={cn('size-5 shrink-0', TONE[tone])}
                    aria-hidden
                />
            </CardContent>
        </Card>
    );
}
