import { cn } from '@/lib/utils';
import type { TrendDay } from '@/types/models';

type Props = {
    days: TrendDay[];
};

/**
 * Uploads per day as a bar chart, drawn with divs — no chart library for
 * fourteen numbers. The data table underneath it is what a screen reader reads,
 * so the bars are decorative (DESIGN §6).
 */
export function UploadTrend({ days }: Props) {
    const peak = Math.max(...days.map((day) => day.count), 1);
    const total = days.reduce((sum, day) => sum + day.count, 0);

    return (
        <div className="space-y-3">
            <div
                className="flex h-28 items-end gap-1.5"
                role="img"
                aria-label={`${total} uploads over the last ${days.length} days`}
            >
                {days.map((day) => {
                    const height = day.count === 0 ? 2 : (day.count / peak) * 100;

                    return (
                        <div
                            key={day.date}
                            className="flex min-w-0 flex-1 flex-col items-center gap-1"
                            title={`${day.label}: ${day.count}`}
                        >
                            <span
                                className={cn(
                                    'w-full rounded-t-sm transition-[height]',
                                    day.count === 0
                                        ? 'bg-border'
                                        : 'bg-primary/80',
                                )}
                                style={{ height: `${height}%` }}
                            />
                        </div>
                    );
                })}
            </div>

            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>{days[0]?.label}</span>
                <span>{days[days.length - 1]?.label}</span>
            </div>
        </div>
    );
}
