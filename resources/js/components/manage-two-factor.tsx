import { router } from '@inertiajs/react';
import { Check, ShieldCheck, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    confirm,
    disable,
    enable,
    qrCode,
    recoveryCodes as recoveryCodesRoute,
    regenerateRecoveryCodes,
    secretKey,
} from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

async function fetchJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Request to ${url} failed with ${response.status}`);
    }

    return (await response.json()) as T;
}

type SetupPayload = {
    svg: string;
    secret: string;
    recoveryCodes: string[];
};

/**
 * Two-factor enrolment + management. The QR code, secret and recovery codes are
 * fetched from Fortify's endpoints only while the panel is open, so they never sit
 * in page props (they are credentials).
 */
export default function ManageTwoFactor({
    canManageTwoFactor = false,
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    const [setup, setSetup] = useState<SetupPayload | null>(null);
    const [busy, setBusy] = useState(false);
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | undefined>(undefined);

    if (!canManageTwoFactor) {
        return null;
    }

    async function loadSetup(): Promise<void> {
        const [svg, secret, recoveryCodes] = await Promise.all([
            fetchJson<{ svg: string }>(qrCode.url()),
            fetchJson<{ secretKey: string }>(secretKey.url()),
            fetchJson<string[]>(recoveryCodesRoute.url()),
        ]);

        setSetup({
            svg: svg.svg,
            secret: secret.secretKey,
            recoveryCodes,
        });
    }

    function startEnrolment(): void {
        setBusy(true);
        setError(undefined);

        router.post(
            enable.url(),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    void loadSetup().finally(() => setBusy(false));
                },
                onError: () => {
                    setBusy(false);
                    setError('Two-factor authentication could not be enabled.');
                },
            },
        );
    }

    function confirmEnrolment(event: React.FormEvent): void {
        event.preventDefault();
        setBusy(true);
        setError(undefined);

        router.post(
            confirm.url(),
            { code },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSetup(null);
                    setCode('');
                    setBusy(false);
                },
                onError: (errors) => {
                    setBusy(false);
                    setError(
                        typeof errors.code === 'string'
                            ? errors.code
                            : 'That code was not valid. Try the next one.',
                    );
                },
                onFinish: () => setBusy(false),
            },
        );
    }

    function turnOff(): void {
        setBusy(true);

        router.delete(disable.url(), {
            preserveScroll: true,
            onFinish: () => {
                setSetup(null);
                setBusy(false);
            },
        });
    }

    function newRecoveryCodes(): void {
        setBusy(true);

        router.post(
            regenerateRecoveryCodes.url(),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    void loadSetup().finally(() => setBusy(false));
                },
                onFinish: () => setBusy(false),
            },
        );
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Two-factor authentication"
                description="Require an authenticator code in addition to your password"
            />

            <div className="space-y-4 rounded-lg border border-border p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="inline-flex items-center gap-2 text-sm font-medium">
                        {twoFactorEnabled ? (
                            <>
                                <ShieldCheck
                                    className="size-4 text-emerald-600"
                                    aria-hidden
                                />
                                Enabled
                            </>
                        ) : (
                            <>
                                <ShieldOff
                                    className="size-4 text-muted-foreground"
                                    aria-hidden
                                />
                                Not enabled
                            </>
                        )}
                    </span>

                    <div className="flex items-center gap-2">
                        {twoFactorEnabled ? (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={busy}
                                    onClick={newRecoveryCodes}
                                >
                                    {busy && <Spinner />}
                                    Show recovery codes
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    disabled={busy}
                                    onClick={turnOff}
                                >
                                    Turn off
                                </Button>
                            </>
                        ) : (
                            <Button
                                size="sm"
                                disabled={busy}
                                onClick={startEnrolment}
                                data-test="enable-two-factor-button"
                            >
                                {busy && <Spinner />}
                                Set up
                            </Button>
                        )}
                    </div>
                </div>

                <InputError message={error} />

                {setup !== null && (
                    <div className="space-y-4 border-t pt-4">
                        <div className="flex flex-wrap items-start gap-6">
                            <div
                                className="rounded-lg bg-white p-3 [&_svg]:size-40"
                                // Fortify returns a self-contained SVG for the QR code.
                                dangerouslySetInnerHTML={{ __html: setup.svg }}
                            />
                            <div className="space-y-2 text-sm">
                                <p className="max-w-sm text-muted-foreground">
                                    Scan the QR code with your authenticator
                                    app, or enter the setup key manually.
                                </p>
                                <p className="font-mono text-xs break-all select-all">
                                    {setup.secret}
                                </p>
                            </div>
                        </div>

                        {setup.recoveryCodes.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-sm font-medium">
                                    Recovery codes
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Store these somewhere safe. Each one signs
                                    you in once if you lose your authenticator.
                                </p>
                                <ul className="grid gap-1 rounded-md bg-muted p-3 font-mono text-xs sm:grid-cols-2">
                                    {setup.recoveryCodes.map((recoveryCode) => (
                                        <li
                                            key={recoveryCode}
                                            className="select-all"
                                        >
                                            {recoveryCode}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        {requiresConfirmation && !twoFactorEnabled && (
                            <form
                                onSubmit={confirmEnrolment}
                                className="flex flex-wrap items-end gap-3"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="two_factor_code">
                                        Authenticator code
                                    </Label>
                                    <Input
                                        id="two_factor_code"
                                        value={code}
                                        onChange={(event) =>
                                            setCode(event.target.value)
                                        }
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        required
                                        className="w-32 font-mono tracking-widest"
                                    />
                                </div>
                                <Button type="submit" disabled={busy}>
                                    {busy ? (
                                        <Spinner />
                                    ) : (
                                        <Check className="size-4" aria-hidden />
                                    )}
                                    Confirm
                                </Button>
                            </form>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
