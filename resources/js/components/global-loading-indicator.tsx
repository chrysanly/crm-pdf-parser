import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * The app-wide "something is happening" signal: a determinate bar across the top
 * for every Inertia visit, and — only once a visit has been slow enough to
 * notice — a small status pill.
 *
 * Mounted once in app.tsx, which is why Inertia's own progress bar is turned
 * off there: two bars for one visit is worse than none.
 */
export function GlobalLoadingIndicator() {
    const [progress, setProgress] = useState<number | null>(null);
    const [slow, setSlow] = useState(false);

    useEffect(() => {
        let slowTimer: number | undefined;
        let doneTimer: number | undefined;
        let creep: number | undefined;

        const clearTimers = () => {
            window.clearTimeout(slowTimer);
            window.clearTimeout(doneTimer);
            window.clearInterval(creep);
        };

        const offStart = router.on('start', () => {
            clearTimers();
            setProgress(8);
            slowTimer = window.setTimeout(() => setSlow(true), 400);

            // Uploads report real progress; plain visits do not, so the bar
            // creeps forward to show the request is still alive.
            creep = window.setInterval(() => {
                setProgress((current) =>
                    current === null ? 8 : Math.min(current + (90 - current) * 0.1, 90),
                );
            }, 300);
        });

        const offProgress = router.on('progress', (event) => {
            const percentage = event.detail.progress?.percentage;

            if (typeof percentage === 'number') {
                window.clearInterval(creep);
                setProgress(Math.max(8, Math.min(percentage, 96)));
            }
        });

        const offFinish = router.on('finish', () => {
            clearTimers();
            setSlow(false);
            setProgress(100);
            doneTimer = window.setTimeout(() => setProgress(null), 320);
        });

        return () => {
            offStart();
            offProgress();
            offFinish();
            clearTimers();
        };
    }, []);

    return (
        <>
            <div
                className="pointer-events-none fixed inset-x-0 top-0 z-100 h-0.5"
                role="progressbar"
                aria-hidden={progress === null}
                aria-label="Page loading"
                aria-valuenow={progress ?? 0}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className={cn(
                        'h-full bg-primary transition-[width,opacity] duration-200 ease-out',
                        progress === null ? 'opacity-0' : 'opacity-100',
                    )}
                    style={{ width: `${progress ?? 0}%` }}
                />
            </div>

            {slow && (
                <div
                    className="pointer-events-none fixed bottom-4 left-1/2 z-100 -translate-x-1/2"
                    role="status"
                    aria-live="polite"
                >
                    <span className="flex items-center gap-2 rounded-full border bg-background/95 px-3 py-1.5 text-xs font-medium shadow-lg backdrop-blur">
                        <span
                            className="size-3 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-foreground"
                            aria-hidden
                        />
                        Working…
                    </span>
                </div>
            )}
        </>
    );
}
