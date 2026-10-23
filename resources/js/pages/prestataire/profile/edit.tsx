import { Head, useForm } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    CheckCircle2,
    Clock3,
    MapPin,
    Save,
    Wrench,
} from 'lucide-react';
import type { FormEvent } from 'react';

type ProviderProfile = {
    business_name: string | null;
    bio: string | null;
    phone: string | null;
    years_experience: number | null;
    city: string | null;
    neighborhood: string | null;
    address: string | null;
    status: string;
};

type ServiceItem = {
    id: number;
    name: string;
    description: string | null;
    pricing_type: 'fixed' | 'quote';
    base_price: string | number | null;
    estimated_duration_minutes: number | null;
    category: {
        id: number;
        name: string;
    };
};

type Props = {
    profile: ProviderProfile | null;
    services: ServiceItem[];
    selectedServiceIds: number[];
};

function formatPrice(price: string | number): string {
    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(Number(price))} FCFA`;
}

function formatDuration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return remainingMinutes > 0
        ? `${hours} h ${remainingMinutes} min`
        : `${hours} h`;
}

export default function EditProviderProfile({
    profile,
    services,
    selectedServiceIds,
}: Props) {
    const form = useForm({
        business_name: profile?.business_name ?? '',
        bio: profile?.bio ?? '',
        phone: profile?.phone ?? '',
        years_experience: profile?.years_experience ?? ('' as number | ''),
        city: profile?.city ?? '',
        neighborhood: profile?.neighborhood ?? '',
        address: profile?.address ?? '',
        services: selectedServiceIds,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.put('/prestataire/profile');
    }

    return (
        <>
            <Head title="Mon profil professionnel" />

            <div className="mx-auto flex max-w-4xl flex-col gap-8 p-4 md:p-8">
                <header>
                    <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <BriefcaseBusiness className="size-6" />
                    </div>

                    <h1 className="mt-4 text-3xl font-bold tracking-tight">
                        Mon profil professionnel
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Présentez votre activité et les services que vous
                        proposez aux clients.
                    </p>
                </header>

                {profile?.status === 'approved' ? (
                    <p className="rounded-lg border p-4 text-sm">
                        Votre profil professionnel est validé.
                    </p>
                ) : (
                    <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                        Votre profil devra être validé par l'administration
                        avant d'être proposé aux clients.
                    </p>
                )}

                <form onSubmit={submit} className="flex flex-col gap-6">
                    {/* Informations professionnelles */}
                    <section className="rounded-xl border bg-card p-5 md:p-6">
                        <h2 className="text-lg font-semibold">
                            Informations professionnelles
                        </h2>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <label
                                    htmlFor="business_name"
                                    className="text-sm font-medium"
                                >
                                    Nom commercial
                                </label>

                                <input
                                    id="business_name"
                                    value={form.data.business_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'business_name',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={150}
                                    placeholder="Ex. : Jean Plomberie"
                                    className="rounded-lg border bg-background px-3 py-2 text-sm"
                                />

                                {form.errors.business_name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.business_name}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <label
                                    htmlFor="phone"
                                    className="text-sm font-medium"
                                >
                                    Téléphone professionnel
                                </label>

                                <input
                                    id="phone"
                                    type="tel"
                                    value={form.data.phone}
                                    onChange={(event) =>
                                        form.setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={30}
                                    placeholder="Ex. : 07 XX XX XX XX"
                                    className="rounded-lg border bg-background px-3 py-2 text-sm"
                                />

                                {form.errors.phone && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.phone}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2 md:col-span-2">
                                <label
                                    htmlFor="bio"
                                    className="text-sm font-medium"
                                >
                                    Présentation
                                </label>

                                <textarea
                                    id="bio"
                                    value={form.data.bio}
                                    onChange={(event) =>
                                        form.setData('bio', event.target.value)
                                    }
                                    maxLength={3000}
                                    rows={4}
                                    placeholder="Présentez votre expérience et votre manière de travailler."
                                    className="resize-y rounded-lg border bg-background px-3 py-2 text-sm"
                                />

                                {form.errors.bio && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.bio}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <label
                                    htmlFor="years_experience"
                                    className="text-sm font-medium"
                                >
                                    Années d'expérience
                                </label>

                                <input
                                    id="years_experience"
                                    type="number"
                                    min={0}
                                    max={80}
                                    value={form.data.years_experience}
                                    onChange={(event) =>
                                        form.setData(
                                            'years_experience',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                    className="rounded-lg border bg-background px-3 py-2 text-sm"
                                />

                                {form.errors.years_experience && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.years_experience}
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>

                    {/* Services proposés */}
                    <section className="rounded-xl border bg-card p-5 md:p-6">
                        <div className="flex items-center gap-3">
                            <Wrench className="size-5 text-primary" />

                            <div>
                                <h2 className="text-lg font-semibold">
                                    Mes services
                                </h2>

                                <p className="mt-1 text-sm text-muted-foreground">
                                    Sélectionnez les prestations que vous êtes
                                    capable de réaliser.
                                </p>
                            </div>
                        </div>

                        {form.errors.services && (
                            <p className="mt-3 text-sm text-destructive">
                                {form.errors.services}
                            </p>
                        )}

                        {services.length > 0 ? (
                            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                {services.map((service) => {
                                    const checked = form.data.services.includes(
                                        service.id,
                                    );

                                    return (
                                        <label
                                            key={service.id}
                                            className={`flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors ${
                                                checked
                                                    ? 'border-primary bg-primary/5'
                                                    : 'hover:bg-accent/30'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={(event) => {
                                                    const isChecked =
                                                        event.target.checked;

                                                    form.setData(
                                                        'services',
                                                        isChecked
                                                            ? [
                                                                  ...form.data
                                                                      .services,
                                                                  service.id,
                                                              ]
                                                            : form.data.services.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      service.id,
                                                              ),
                                                    );
                                                }}
                                                className="mt-1 size-4 accent-primary"
                                            />

                                            <span className="min-w-0 flex-1">
                                                <span className="block text-xs font-medium tracking-wide text-primary uppercase">
                                                    {service.category.name}
                                                </span>

                                                <span className="mt-1 block font-medium">
                                                    {service.name}
                                                </span>

                                                <span className="mt-1 block text-sm text-muted-foreground">
                                                    {service.description ??
                                                        'Description à venir.'}
                                                </span>

                                                <span className="mt-3 flex items-center gap-2 text-sm font-medium">
                                                    <Wrench className="size-4" />

                                                    {service.pricing_type ===
                                                        'fixed' &&
                                                    service.base_price !== null
                                                        ? formatPrice(
                                                              service.base_price,
                                                          )
                                                        : 'Sur devis'}
                                                </span>

                                                {service.estimated_duration_minutes !==
                                                    null && (
                                                    <span className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                                        <Clock3 className="size-3" />

                                                        {formatDuration(
                                                            service.estimated_duration_minutes,
                                                        )}
                                                    </span>
                                                )}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="mt-5 rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                                Aucun service actif n'est disponible pour le
                                moment.
                            </p>
                        )}

                        <p className="mt-4 text-xs text-muted-foreground">
                            Services sélectionnés : {form.data.services.length}
                        </p>
                    </section>

                    {/* Zone d'intervention */}
                    <section className="rounded-xl border bg-card p-5 md:p-6">
                        <div className="flex items-center gap-3">
                            <MapPin className="size-5 text-primary" />

                            <h2 className="text-lg font-semibold">
                                Zone d'intervention
                            </h2>
                        </div>

                        <p className="mt-2 text-sm text-muted-foreground">
                            Indiquez la commune et le quartier où vous
                            intervenez. Cela aidera les clients à vous trouver.
                        </p>

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
                                    value={form.data.city}
                                    onChange={(event) =>
                                        form.setData('city', event.target.value)
                                    }
                                    maxLength={100}
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
                                    value={form.data.neighborhood}
                                    onChange={(event) =>
                                        form.setData(
                                            'neighborhood',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={100}
                                    placeholder="Votre quartier"
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
                                    value={form.data.address}
                                    onChange={(event) =>
                                        form.setData(
                                            'address',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={1000}
                                    rows={2}
                                    placeholder="Précisions sur votre adresse professionnelle ou votre zone."
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

                    {form.recentlySuccessful && (
                        <p className="flex items-center gap-2 text-sm text-green-600">
                            <CheckCircle2 className="size-4" />
                            Votre profil a été enregistré avec succès.
                        </p>
                    )}

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        >
                            <Save className="size-4" />

                            {form.processing
                                ? 'Enregistrement...'
                                : 'Enregistrer mon profil'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
