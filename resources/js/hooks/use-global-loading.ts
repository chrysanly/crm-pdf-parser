import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Whether an Inertia visit is in flight.
 *
 * One source of truth for "the app is busy", so a page never has to invent its
 * own. `delayed` only turns true once the visit has been running long enough to
 * be worth showing — a 60ms navigation should not flash a spinner (DESIGN §10).
 */
export function useGlobalLoading(delayMs = 250): {
    loading: boolean;
    delayed: boolean;
} {
    const [loading, setLoading] = useState(false);
    const [delayed, setDelayed] = useState(false);

    useEffect(() => {
        let timer: number | undefined;

        const clear = () => {
            if (timer !== undefined) {
                window.clearTimeout(timer);
                timer = undefined;
            }
        };

        const offStart = router.on('start', () => {
            setLoading(true);
            clear();
            timer = window.setTimeout(() => setDelayed(true), delayMs);
        });

        const offFinish = router.on('finish', () => {
            setLoading(false);
            setDelayed(false);
            clear();
        });

        return () => {
            offStart();
            offFinish();
            clear();
        };
    }, [delayMs]);

    return { loading, delayed };
}
