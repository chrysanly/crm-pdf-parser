import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    /** The button that opens the dialog — the caller owns its wording and style. */
    trigger: React.ReactNode;
    title: string;
    description: string;
    confirmLabel: string;
    pendingLabel: string;
    /** Endpoint to hit on confirm. */
    url: string;
    method?: 'delete' | 'post' | 'put';
};

/**
 * Confirm-then-act for destructive buttons, with the pending state built in:
 * once confirmed, both buttons are disabled until the request settles, so the
 * action cannot be fired twice (DESIGN §4).
 */
export function ConfirmDestructiveDialog({
    trigger,
    title,
    description,
    confirmLabel,
    pendingLabel,
    url,
    method = 'delete',
}: Props) {
    const [pending, setPending] = useState(false);

    function confirm() {
        setPending(true);

        router[method](
            url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setPending(false),
            },
        );
    }

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    {/* Destructive copy names the object (DESIGN §4). */}
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline" disabled={pending}>
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={confirm}
                        disabled={pending}
                        aria-busy={pending}
                    >
                        {pending && <Spinner />}
                        {pending ? pendingLabel : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
