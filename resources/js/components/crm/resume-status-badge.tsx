import { CheckCircle2, Clock, Loader2, TriangleAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ResumeStatusColor, ResumeStatusValue } from '@/types/models';

type Props = {
    status: ResumeStatusValue;
    /** Label comes from the PHP enum via the Resource — never mapped here (DESIGN §5). */
    label: string;
    color: ResumeStatusColor;
    className?: string;
};

const TONE: Record<ResumeStatusColor, string> = {
    neutral: 'border-border bg-muted text-muted-foreground',
    info: 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300',
    success:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    danger: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
};

const ICON: Record<ResumeStatusValue, typeof Clock> = {
    pending: Clock,
    processing: Loader2,
    parsed: CheckCircle2,
    failed: TriangleAlert,
};

// Colour is never the only signal — the label always ships with it (DESIGN §6).
export function ResumeStatusBadge({ status, label, color, className }: Props) {
    const Icon = ICON[status];

    return (
        <Badge
            variant="outline"
            className={cn('gap-1.5 font-medium', TONE[color], className)}
        >
            <Icon
                className={cn('size-3.5', status === 'processing' && 'animate-spin')}
                aria-hidden
            />
            {label}
        </Badge>
    );
}
