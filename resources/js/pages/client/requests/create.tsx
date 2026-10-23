import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    MapPin,
    Send,
    Wrench,
} from 'lucide-react';
import type { FormEvent } from 'react';

type Service = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    pricing_type: 'fixed' | 'quote';
    base_price: string | number | null;
    category: {
        name: string;
        slug: string;
    };
};

type Provider = {
    id: number;
    name: string;
    business_name: string | null;
    city: string | null;
    neighborhood: string | null;
    is_available: boolean;
    price: string | number | null;
};

type Props = {
    service: Service;
    provider: Provider;
};

function getPriceLabel(service: Service, provider: Provider): string {
    const price = provider.price ?? service.base_price;

    if (
        price !== null &&
        price !== undefined &&
        (provider.price !== null || service.pricing_type === 'fixed')
    ) {
        return `${new Intl.NumberFormat('fr-FR', {
            maximumFractionDigits: 0,
        }).format(Number(price))} FCFA`;
    }

    return 'Sur devis';
}

export default function CreateServiceRequest({ service, provider }: Props) {
    const form = useForm({
        description: '',
        city: provider.city ?? '',
        neighborhood: provider.neighborhood ?? '',
        address: '',
        scheduled_at: '',
        is_urgent: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(
            `/client/services/${service.slug}/providers/${provider.id}/request`,
        );
    }

    return (
        <>
            <Head title="Demander une prestation" />

            <div className="mx-auto flex max-w-4xl flex-col gap-8 p-4 md:p-8">
                <Link
                    href={`/client/services/${service.slug}/providers/${provider.id}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour au profil
                </Link>

                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Nouvelle demande
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        Demander une prestation
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Décrivez votre besoin pour permettre au prestataire de
                        comprendre le travail demandé.
                    </p>
                </header>

                {/* Récapitulatif */}
                <section className="rounded-xl border bg-card p-5">
                    <div className="flex items-start gap-4">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Wrench className="size-6" />
                        </div>

                        <div className="min-w-0 flex-1">
                            <p className="text-sm text-muted-foreground">
                                {service.category.name}
                            </p>

                            <h2 className="mt-1 text-xl font-semibold">
                                {service.name}
                            </h2>

                            <p className="mt-2 text-sm text-muted-foreground">
                                Prestataire :{' '}
                                {provider.business_name || provider.name}
                            </p>

                            <p className="mt-3 font-semibold">
                                {getPriceLabel(service, provider)}
                            </p>

                            {provider.is_available ? (
                                <span className="mt-3 inline-flex items-center gap-2 text-sm text-green-700 dark:text-green-400">
                                    <CheckCircle2 className="size-4" />
                                    Disponible actuellement
                                </span>
                            ) : (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Disponibilité à confirmer avec le
                                    prestataire.
                                </p>
                            )}
                        </div>
                    </div>
                </section>

                {/* Formulaire */}
                <form onSubmit={submit} className="flex flex-col gap-6">
                    <section className="rounded-xl border bg-card p-5 md:p-6">
                        <h2 className="text-lg font-semibold">
                            Décrivez votre besoin
                        </h2>

                        <div className="mt-5 flex flex-col gap-2">
                            <label
                                htmlFor="description"
                                className="text-sm font-medium"
                            >
                                Description du problème *
                            </label>

                            <textarea
                                id="description"
                                required
                                minLength={10}
                                maxLength={3000}
                                rows={5}
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Ex. : le robinet de ma cuisine fuit et l'eau continue de couler."
                                className="resize-y rounded-lg border bg-background px-3 py-2 text-sm"
                            />

                            <p className="text-xs text-muted-foreground">
                                Minimum 10 caractères, maximum 3 000.
                            </p>

                            {form.errors.description && (
                                <p className="text-sm text-destructive">
                                    {form.errors.description}
                                </p>
                            )}
                        </div>
                    </section>

                    <section className="rounded-xl border bg-card p-5 md:p-6">
                        <div className="flex items-center gap-3">
                            <MapPin className="size-5 text-primary" />

                            <h2 className="text-lg font-semibold">
                                Lieu de l'intervention
                            </h2>
                        </div>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <label
                                    htmlFor="city"
                                    className="text-sm font-medium"
                                >
                                    Commune ou ville *
                                </label>

                                <input
                                    id="city"
                                    required
                                    maxLength={100}
                                    value={form.data.city}
                                    onChange={(event) =>
                                        form.setData('city', event.target.value)
                                    }
                                    placeholder="Ex. : Anyama"
                                    className="rounded-lg border bg-background px-3 py-2 text-sm"
                                />

                                {form.errors.city && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.city}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <label
                                    htmlFor="neighborhood"
                                    className="text-sm font-medium"
                                >
                                    Quartier *
                                </label>

                                <input
                                    id="neighborhood"
                                    required
                                    maxLength={100}
                                    value={form.data.neighborhood}
                                    onChange={(event) =>
                                        form.setData(
                                            'neighborhood',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Ex. : Centre"
                                    className="rounded-lg border bg-background px-3 py-2 text-sm"
                                />

                                {form.errors.neighborhood && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.neighborhood}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2 md:col-span-2">
                                <label
                                    htmlFor="address"
                                    className="text-sm font-medium"
                                >
                                    Adresse complémentaire (facultatif)
                                </label>

                                <textarea
                                    id="address"
                                    maxLength={255}
                                    rows={2}
                                    value={form.data.address}
                                    onChange={(event) =>
                                        form.setData(
                                            'address',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Précisez l'adresse ou un point de repère."
                                    className="resize-y rounded-lg border bg-background px-3 py-2 text-sm"
                                />

                                {form.errors.address && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.address}
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="rounded-xl border bg-card p-5 md:p-6">
                        <div className="flex items-center gap-3">
                            <CalendarClock className="size-5 text-primary" />

                            <h2 className="text-lg font-semibold">
                                Date et urgence
                            </h2>
                        </div>

                        <div className="mt-5 flex flex-col gap-2">
                            <label
                                htmlFor="scheduled_at"
                                className="text-sm font-medium"
                            >
                                Date souhaitée (facultatif)
                            </label>

                            <input
                                id="scheduled_at"
                                type="datetime-local"
                                value={form.data.scheduled_at}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_at',
                                        event.target.value,
                                    )
                                }
                                className="rounded-lg border bg-background px-3 py-2 text-sm"
                            />

                            {form.errors.scheduled_at && (
                                <p className="text-sm text-destructive">
                                    {form.errors.scheduled_at}
                                </p>
                            )}
                        </div>

                        <label className="mt-6 flex cursor-pointer items-start gap-3 rounded-lg border p-4">
                            <input
                                type="checkbox"
                                checked={form.data.is_urgent}
                                onChange={(event) =>
                                    form.setData(
                                        'is_urgent',
                                        event.target.checked,
                                    )
                                }
                                className="mt-1 size-4 accent-primary"
                            />

                            <span>
                                <span className="block font-medium">
                                    Intervention urgente
                                </span>

                                <span className="mt-1 block text-sm text-muted-foreground">
                                    Cochez cette case si votre situation
                                    nécessite une intervention rapide. Le
                                    prestataire devra confirmer qu'il peut
                                    intervenir.
                                </span>
                            </span>
                        </label>

                        {form.errors.is_urgent && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.is_urgent}
                            </p>
                        )}
                    </section>

                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <Link
                            href={`/client/services/${service.slug}/providers/${provider.id}`}
                            className="text-center text-sm text-muted-foreground hover:text-foreground"
                        >
                            Annuler
                        </Link>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        >
                            <Send className="size-4" />

                            {form.processing
                                ? 'Envoi en cours...'
                                : 'Envoyer ma demande'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
