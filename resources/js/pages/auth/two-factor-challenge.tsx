import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function TwoFactorChallenge() {
    return (
        <main className="mx-auto flex min-h-svh w-full max-w-md flex-col justify-center gap-8 px-4 py-8">
            <Head title="Vérification de sécurité" />
            <header className="space-y-2">
                <h1 className="text-2xl font-semibold">
                    Vérification de sécurité
                </h1>
                <p className="text-sm text-muted-foreground">
                    Saisis le code temporaire de ton application
                    d’authentification.
                </p>
            </header>

            <Form
                action="/two-factor-challenge"
                method="post"
                className="space-y-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code à six chiffres</Label>
                            <Input
                                id="code"
                                name="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                autoFocus
                            />
                            <InputError message={errors.code} />
                        </div>
                        <Button className="w-full" disabled={processing}>
                            Vérifier
                        </Button>
                    </>
                )}
            </Form>

            <details className="rounded-lg border p-4">
                <summary className="cursor-pointer font-medium">
                    Utiliser un code de secours
                </summary>
                <Form
                    action="/two-factor-challenge"
                    method="post"
                    className="mt-4 space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="recovery_code">
                                    Code de secours
                                </Label>
                                <Input
                                    id="recovery_code"
                                    name="recovery_code"
                                    autoComplete="one-time-code"
                                />
                                <InputError message={errors.recovery_code} />
                            </div>
                            <Button className="w-full" disabled={processing}>
                                Vérifier le code de secours
                            </Button>
                        </>
                    )}
                </Form>
            </details>
        </main>
    );
}
