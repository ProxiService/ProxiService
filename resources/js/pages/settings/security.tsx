import { Form, Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    canManageTwoFactor?: boolean;
    twoFactorEnabled?: boolean;
    twoFactorConfirmed?: boolean;
    twoFactorRecoveryCodes?: string[];
} & ManagePasskeysProps;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const twoFactorForm = useForm({ code: '' });

    function confirmTwoFactor(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        twoFactorForm.post('/user/confirmed-two-factor-authentication', {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['twoFactorConfirmed'] }),
        });
    }

    return (
        <>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Update password"
                    description="Ensure your account is using a long, random password to stay secure"
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Current password
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder="Current password"
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="New password"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />

            {props.canManageTwoFactor && (
                <section
                    className="space-y-4 rounded-lg border p-4"
                    aria-label="Double authentification"
                >
                    <Heading
                        variant="small"
                        title="Double authentification"
                        description="Ajoute un code temporaire généré par une application d’authentification. Cette étape est obligatoire pour accéder à l’administration."
                    />
                    {!props.twoFactorEnabled && (
                        <Button
                            type="button"
                            onClick={() =>
                                router.post('/user/two-factor-authentication')
                            }
                        >
                            Activer la double authentification
                        </Button>
                    )}
                    {props.twoFactorEnabled && !props.twoFactorConfirmed && (
                        <div className="space-y-4">
                            <p className="text-sm">
                                Scanne ce QR code avec ton application, puis
                                saisis le code à six chiffres.
                            </p>
                            <img
                                src="/user/two-factor-qr-code"
                                alt="QR code de configuration de la double authentification"
                                className="h-48 w-48"
                            />
                            <form
                                onSubmit={confirmTwoFactor}
                                className="flex flex-wrap items-end gap-3"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="two-factor-code">
                                        Code de confirmation
                                    </Label>
                                    <input
                                        id="two-factor-code"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        className="h-11 rounded-md border bg-background px-3 text-base"
                                        value={twoFactorForm.data.code}
                                        onChange={(event) =>
                                            twoFactorForm.setData(
                                                'code',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={twoFactorForm.errors.code}
                                    />
                                </div>
                                <Button disabled={twoFactorForm.processing}>
                                    Confirmer
                                </Button>
                            </form>
                        </div>
                    )}
                    {props.twoFactorConfirmed && (
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-green-700">
                                La double authentification est activée et
                                confirmée. Conserve ces codes de secours dans un
                                endroit sûr.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        '/user/two-factor-recovery-codes',
                                        {},
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                router.reload({
                                                    only: [
                                                        'twoFactorRecoveryCodes',
                                                    ],
                                                }),
                                        },
                                    )
                                }
                            >
                                Générer de nouveaux codes de secours
                            </Button>
                            <ul className="grid gap-1 rounded-md bg-muted p-3 font-mono text-sm sm:grid-cols-2">
                                {props.twoFactorRecoveryCodes?.map((code) => (
                                    <li key={code}>{code}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </section>
            )}
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
