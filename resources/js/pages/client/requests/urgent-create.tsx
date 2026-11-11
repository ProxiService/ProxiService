import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LocateFixed, Send, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';

type Props = {
    services: Array<{ id: number; name: string; category: string | null }>;
};
type Draft = {
    service_id?: string;
    description?: string;
    city?: string;
    neighborhood?: string;
};
const draftKey = 'proxiservices.urgent-draft.v1';

export default function UrgentRequestCreate({ services }: Props) {
    const [step, setStep] = useState(1);
    const [locationMessage, setLocationMessage] = useState('');
    const [draft] = useState<Draft | null>(() => {
        try {
            const value = sessionStorage.getItem(draftKey);
            return value ? (JSON.parse(value) as Draft) : null;
        } catch {
            return null;
        }
    });
    const form = useForm({
        service_id: draft?.service_id ?? '',
        description: draft?.description ?? '',
        city: draft?.city ?? '',
        neighborhood: draft?.neighborhood ?? '',
        location_consent: false,
        latitude: '',
        longitude: '',
    });

    useEffect(() => {
        try {
            const { service_id, description, city, neighborhood } = form.data;
            if (service_id || description || city || neighborhood) {
                sessionStorage.setItem(
                    draftKey,
                    JSON.stringify({
                        service_id,
                        description,
                        city,
                        neighborhood,
                    }),
                );
            } else {
                sessionStorage.removeItem(draftKey);
            }
        } catch {
            // Le formulaire reste utilisable si le stockage local est indisponible.
        }
    }, [
        form.data.service_id,
        form.data.description,
        form.data.city,
        form.data.neighborhood,
    ]);

    function goNext() {
        form.clearErrors();
        if (step === 1 && !form.data.service_id) {
            form.setError('service_id', 'Choisissez le type de service.');
            return;
        }
        if (step === 1 && form.data.description.trim().length < 10) {
            form.setError(
                'description',
                'Décrivez brièvement le problème (10 caractères minimum).',
            );
            return;
        }
        if (step === 2 && !form.data.city.trim()) {
            form.setError('city', 'Indiquez votre commune.');
            return;
        }
        if (step === 2 && !form.data.neighborhood.trim()) {
            form.setError('neighborhood', 'Indiquez votre quartier.');
            return;
        }
        setStep((current) => Math.min(3, current + 1));
    }

    function locate() {
        if (!form.data.location_consent) {
            setLocationMessage(
                'Veuillez d’abord accepter l’utilisation de votre position.',
            );
            return;
        }
        if (!navigator.geolocation) {
            setLocationMessage(
                'La géolocalisation est indisponible sur cet appareil. La demande ne peut pas être envoyée.',
            );
            return;
        }
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                form.setData((data) => ({
                    ...data,
                    latitude: String(coords.latitude),
                    longitude: String(coords.longitude),
                }));
                setLocationMessage(
                    'Position reçue. Elle reste privée et sert uniquement à rechercher des prestataires proches.',
                );
            },
            () => {
                form.setData((data) => ({
                    ...data,
                    latitude: '',
                    longitude: '',
                }));
                setLocationMessage(
                    'Position refusée ou indisponible. Autorisez le GPS pour continuer.',
                );
            },
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 },
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (
            !form.data.location_consent ||
            !form.data.latitude ||
            !form.data.longitude
        ) {
            setLocationMessage(
                'Votre accord et une position GPS disponible sont nécessaires pour diffuser la demande.',
            );
            return;
        }
        form.post('/client/requests/urgent', {
            onSuccess: () => sessionStorage.removeItem(draftKey),
        });
    }

    function clearDraft() {
        form.setData({
            service_id: '',
            description: '',
            city: '',
            neighborhood: '',
            location_consent: false,
            latitude: '',
            longitude: '',
        });
        form.clearErrors();
        sessionStorage.removeItem(draftKey);
        setLocationMessage('');
        setStep(1);
    }

    return (
        <>
            <Head title="Demande urgente" />
            <main className="mx-auto flex min-h-dvh max-w-2xl flex-col gap-6 px-4 pt-4 pb-[calc(6rem+env(safe-area-inset-bottom))] md:p-8">
                <Link
                    href="/client/dashboard"
                    className="inline-flex min-h-11 w-fit items-center gap-2 text-sm text-muted-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour
                </Link>
                <header>
                    <p className="font-semibold text-orange-700">
                        Mode Urgence
                    </p>
                    <h1 className="mt-2 text-2xl font-bold">
                        Décrivez votre problème
                    </h1>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Un parcours guidé en trois étapes. Les prestataires
                        disponibles à proximité seront recherchés après votre
                        accord GPS. Aucun paiement n’est demandé à cette étape.
                    </p>
                    <div className="mt-4" aria-label={`Étape ${step} sur 3`}>
                        <p className="text-sm font-semibold">
                            Étape {step} sur 3
                        </p>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-orange-600 transition-[width] motion-reduce:transition-none"
                                style={{ width: `${(step * 100) / 3}%` }}
                            />
                        </div>
                    </div>
                </header>
                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-2xl border bg-card p-4 sm:p-5"
                >
                    <p className="text-xs text-muted-foreground">
                        Votre brouillon reste dans cet onglet jusqu’à l’envoi,
                        son effacement ou sa fermeture. Les coordonnées GPS ne
                        sont jamais conservées dans le brouillon.
                    </p>
                    <section hidden={step !== 1} aria-label="Décrire le besoin">
                        <div>
                            <label
                                htmlFor="service_id"
                                className="mb-1 block text-sm font-medium"
                            >
                                Type de service
                            </label>
                            <select
                                id="service_id"
                                required={step === 1}
                                value={form.data.service_id}
                                onChange={(e) =>
                                    form.setData('service_id', e.target.value)
                                }
                                className="min-h-12 w-full rounded-lg border bg-background px-3 text-base"
                            >
                                <option value="">Choisir un service</option>
                                {services.map((service) => (
                                    <option key={service.id} value={service.id}>
                                        {service.category
                                            ? `${service.category} · `
                                            : ''}
                                        {service.name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.service_id && (
                                <p className="mt-1 text-sm text-red-700">
                                    {form.errors.service_id}
                                </p>
                            )}
                        </div>
                        <div className="mt-5">
                            <label
                                htmlFor="description"
                                className="mb-1 block text-sm font-medium"
                            >
                                Que se passe-t-il ?
                            </label>
                            <textarea
                                id="description"
                                required={step === 1}
                                minLength={10}
                                maxLength={500}
                                rows={4}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                className="w-full rounded-lg border bg-background p-3 text-base"
                                placeholder="Ex. Une fuite d’eau sous l’évier."
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                {form.data.description.length}/500 caractères
                            </p>
                            {form.errors.description && (
                                <p className="mt-1 text-sm text-red-700">
                                    {form.errors.description}
                                </p>
                            )}
                        </div>
                    </section>
                    <section
                        hidden={step !== 2}
                        aria-label="Indiquer le quartier"
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <div>
                            <label
                                htmlFor="city"
                                className="mb-1 block text-sm font-medium"
                            >
                                Commune
                            </label>
                            <input
                                id="city"
                                required={step === 2}
                                value={form.data.city}
                                onChange={(e) =>
                                    form.setData('city', e.target.value)
                                }
                                className="min-h-12 w-full rounded-lg border bg-background px-3 text-base"
                            />
                            {form.errors.city && (
                                <p className="mt-1 text-sm text-red-700">
                                    {form.errors.city}
                                </p>
                            )}
                        </div>
                        <div>
                            <label
                                htmlFor="neighborhood"
                                className="mb-1 block text-sm font-medium"
                            >
                                Quartier
                            </label>
                            <input
                                id="neighborhood"
                                required={step === 2}
                                value={form.data.neighborhood}
                                onChange={(e) =>
                                    form.setData('neighborhood', e.target.value)
                                }
                                className="min-h-12 w-full rounded-lg border bg-background px-3 text-base"
                            />
                            {form.errors.neighborhood && (
                                <p className="mt-1 text-sm text-red-700">
                                    {form.errors.neighborhood}
                                </p>
                            )}
                        </div>
                    </section>
                    <section
                        hidden={step !== 3}
                        aria-label="Autoriser la localisation"
                        className="space-y-5"
                    >
                        <label className="flex items-start gap-3 text-sm leading-6">
                            <input
                                type="checkbox"
                                checked={form.data.location_consent}
                                onChange={(e) => {
                                    form.setData((data) => ({
                                        ...data,
                                        location_consent: e.target.checked,
                                        latitude: '',
                                        longitude: '',
                                    }));
                                    setLocationMessage('');
                                }}
                                className="mt-1 size-5"
                            />
                            <span>
                                J’accepte d’utiliser ma position GPS pour
                                chercher des prestataires proches. Ma position
                                précise reste privée et n’est pas publiée.
                            </span>
                        </label>
                        <button
                            type="button"
                            onClick={locate}
                            className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border px-4 font-semibold"
                        >
                            <LocateFixed className="size-5" />
                            Utiliser ma position
                        </button>
                        {locationMessage && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {locationMessage}
                            </p>
                        )}
                        {(form.errors.latitude ||
                            form.errors.longitude ||
                            form.errors.location_consent) && (
                            <p className="text-sm text-red-700">
                                Accord et position GPS valides requis.
                            </p>
                        )}
                    </section>
                    <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] backdrop-blur">
                        <div className="mx-auto flex max-w-2xl gap-2">
                            {step > 1 && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        form.clearErrors();
                                        setStep((current) => current - 1);
                                    }}
                                    className="min-h-12 min-w-20 rounded-xl border px-4 font-semibold"
                                >
                                    Retour
                                </button>
                            )}
                            {step < 3 ? (
                                <button
                                    type="button"
                                    onClick={goNext}
                                    className="min-h-12 flex-1 rounded-xl bg-teal-800 px-5 font-bold text-white"
                                >
                                    Continuer
                                </button>
                            ) : (
                                <button
                                    type="submit"
                                    disabled={
                                        form.processing || !form.data.latitude
                                    }
                                    className="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-orange-600 px-4 font-bold text-white disabled:opacity-50"
                                >
                                    <Send className="size-4" />
                                    Diffuser
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={clearDraft}
                                aria-label="Effacer le brouillon"
                                className="inline-flex min-h-12 items-center gap-2 rounded-xl px-3 text-sm text-muted-foreground underline underline-offset-4"
                            >
                                <Trash2 className="size-4" />
                                <span className="hidden sm:inline">
                                    Effacer
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </main>
        </>
    );
}
