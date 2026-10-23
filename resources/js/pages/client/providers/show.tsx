import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    CalendarClock,
    CheckCircle2,
    Clock3,
    MapPin,
    Wrench,
} from 'lucide-react';
import { create as createServiceRequest } from '@/routes/client/services/providers/request';

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
    bio: string | null;
    phone: string | null;
    years_experience: number | null;
    city: string | null;
    neighborhood: string | null;
    is_available: boolean;
    price: string | number | null;
};

type Props = {
    service: Service;
    provider: Provider;
};

function formatPrice(price: string | number): string {
    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(Number(price))} FCFA`;
}

function getPriceLabel(service: Service, provider: Provider): string {
    if (provider.price !== null) {
        return formatPrice(provider.price);
    }

    if (service.pricing_type === 'fixed' && service.base_price !== null) {
        return formatPrice(service.base_price);
    }

    return 'Sur devis';
}

export default function ProviderDetails({ service, provider }: Props) {
    return (
        <>
            <Head
                title={`${provider.business_name || provider.name} - Profil professionnel`}
            />

            <div className="mx-auto flex max-w-4xl flex-col gap-8 p-4 md:p-8">
                <Link
                    href={`/client/services/${service.slug}/providers`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour aux prestataires
                </Link>

                <section className="rounded-2xl border bg-card p-6 md:p-8">
                    <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <div className="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <Wrench className="size-8" />
                        </div>

                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                                ProxiServices · Profil professionnel
                            </p>

                            <h1 className="mt-2 text-3xl font-bold tracking-tight">
                                {provider.business_name || provider.name}
                            </h1>

                            {provider.business_name && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {provider.name}
                                </p>
                            )}

                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <span
                                    className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ${
                                        provider.is_available
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    <span
                                        className={`size-2 rounded-full ${
                                            provider.is_available
                                                ? 'bg-green-600'
                                                : 'bg-muted-foreground'
                                        }`}
                                    />

                                    {provider.is_available
                                        ? 'Disponible'
                                        : 'Non disponible actuellement'}
                                </span>

                                <span className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs">
                                    <CheckCircle2 className="size-3 text-green-600" />
                                    Profil approuvé
                                </span>
                            </div>
                        </div>
                    </div>

                    {provider.bio && (
                        <div className="mt-7 border-t pt-6">
                            <h2 className="font-semibold">Présentation</h2>

                            <p className="mt-2 text-sm leading-7 whitespace-pre-line text-muted-foreground">
                                {provider.bio}
                            </p>
                        </div>
                    )}
                </section>

                <section className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-xl border bg-card p-5">
                        <div className="flex items-center gap-3">
                            <MapPin className="size-5 text-primary" />

                            <h2 className="font-semibold">
                                Zone d'intervention
                            </h2>
                        </div>

                        <p className="mt-3 font-medium">
                            {[provider.city, provider.neighborhood]
                                .filter(Boolean)
                                .join(' · ') || 'Non renseignée'}
                        </p>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Zone déclarée par le prestataire.
                        </p>
                    </div>

                    <div className="rounded-xl border bg-card p-5">
                        <div className="flex items-center gap-3">
                            <BriefcaseBusiness className="size-5 text-primary" />

                            <h2 className="font-semibold">
                                Expérience professionnelle
                            </h2>
                        </div>

                        <p className="mt-3 font-medium">
                            {provider.years_experience !== null
                                ? `${provider.years_experience} année(s)`
                                : 'Non renseignée'}
                        </p>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Expérience déclarée par le professionnel.
                        </p>
                    </div>
                </section>

                <section className="rounded-xl border bg-card p-6">
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        Service consulté
                    </p>

                    <h2 className="mt-2 text-2xl font-bold">{service.name}</h2>

                    <p className="mt-2 text-sm text-muted-foreground">
                        Catégorie : {service.category.name}
                    </p>

                    {service.description && (
                        <p className="mt-4 text-sm leading-6 text-muted-foreground">
                            {service.description}
                        </p>
                    )}

                    <div className="mt-6 flex flex-col gap-4 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Tarif proposé
                            </p>

                            <p className="mt-1 text-2xl font-bold">
                                {getPriceLabel(service, provider)}
                            </p>
                        </div>

                        {provider.is_available ? (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <CalendarClock className="size-5" />
                                Vous pouvez envisager de lui adresser une
                                demande.
                            </div>
                        ) : (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Clock3 className="size-5" />
                                Il n'est pas déclaré disponible actuellement.
                            </div>
                        )}
                    </div>
                </section>

                <div className="mt-6 flex justify-end">
                    <Link
                        href={createServiceRequest({
                            service: service.slug,
                            providerProfile: provider.id,
                        })}
                        className="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-3 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        Demander cette prestation
                    </Link>
                </div>
            </div>
        </>
    );
}
