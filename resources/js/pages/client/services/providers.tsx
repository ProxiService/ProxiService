import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BriefcaseBusiness,
    MapPin,
    Search,
    Wrench,
} from 'lucide-react';
import { show as providerDetails } from '@/routes/client/services/providers';

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
    business_name: string | null;
    bio: string | null;
    years_experience: number | null;
    city: string | null;
    neighborhood: string | null;
    is_available: boolean;
    price: string | number | null;
    user: {
        name: string;
    };
};

type Props = {
    service: Service;
    providers: Provider[];
    filters: {
        city: string;
        neighborhood: string;
    };
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

export default function ServiceProviders({
    service,
    providers,
    filters,
}: Props) {
    const searchAction = `/client/services/${service.slug}/providers`;

    return (
        <>
            <Head title={`Prestataires - ${service.name}`} />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                <Link
                    href={`/client/categories/${service.category.slug}`}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour aux services
                </Link>

                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · {service.category.name}
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        {service.name}
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        {service.description ??
                            'Découvrez les professionnels qui proposent cette prestation.'}
                    </p>
                </header>

                <section className="rounded-xl border bg-card p-5">
                    <div className="flex items-center gap-2">
                        <Search className="size-5 text-primary" />

                        <h2 className="font-semibold">
                            Trouver un professionnel près de chez vous
                        </h2>
                    </div>

                    <form
                        action={searchAction}
                        method="GET"
                        className="mt-4 grid gap-4 sm:grid-cols-2"
                    >
                        <div className="flex flex-col gap-2">
                            <label
                                htmlFor="city"
                                className="text-sm font-medium"
                            >
                                Commune ou ville
                            </label>

                            <input
                                id="city"
                                name="city"
                                defaultValue={filters.city}
                                maxLength={100}
                                placeholder="Ex. : Anyama"
                                className="rounded-lg border bg-background px-3 py-2 text-sm"
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <label
                                htmlFor="neighborhood"
                                className="text-sm font-medium"
                            >
                                Quartier
                            </label>

                            <input
                                id="neighborhood"
                                name="neighborhood"
                                defaultValue={filters.neighborhood}
                                maxLength={100}
                                placeholder="Ex. : Centre"
                                className="rounded-lg border bg-background px-3 py-2 text-sm"
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <button
                                type="submit"
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                            >
                                Filtrer les prestataires
                            </button>
                        </div>
                    </form>
                </section>

                <section>
                    <h2 className="text-xl font-semibold">
                        Prestataires disponibles sur la plateforme
                    </h2>

                    <p className="mt-1 text-sm text-muted-foreground">
                        {providers.length}{' '}
                        {providers.length > 1
                            ? 'professionnels trouvés'
                            : 'professionnel trouvé'}
                    </p>

                    {providers.length > 0 ? (
                        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {providers.map((provider) => (
                                <Link
                                    key={provider.id}
                                    href={providerDetails({
                                        service: service.slug,
                                        providerProfile: provider.id,
                                    })}
                                    className="group flex flex-col rounded-xl border bg-card p-5 transition-colors hover:border-primary/50 hover:bg-accent/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <Wrench className="size-6" />
                                        </div>

                                        <ArrowRight className="size-5 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-primary" />
                                    </div>

                                    <div className="mt-4 flex items-start gap-3">
                                        <div className="min-w-0 flex-1">
                                            <h3 className="font-semibold">
                                                {provider.business_name ||
                                                    provider.user.name}
                                            </h3>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {provider.user.name}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-4 flex items-center gap-2 text-sm">
                                        <MapPin className="size-4 shrink-0 text-muted-foreground" />

                                        <span>
                                            {[
                                                provider.city,
                                                provider.neighborhood,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') ||
                                                'Zone non renseignée'}
                                        </span>
                                    </div>

                                    {provider.years_experience !== null && (
                                        <div className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                            <BriefcaseBusiness className="size-4" />
                                            {provider.years_experience} an(s)
                                            d'expérience
                                        </div>
                                    )}

                                    {provider.bio && (
                                        <p className="mt-4 line-clamp-3 text-sm text-muted-foreground">
                                            {provider.bio}
                                        </p>
                                    )}

                                    <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Tarif
                                            </p>

                                            <p className="mt-1 font-semibold">
                                                {getPriceLabel(
                                                    service,
                                                    provider,
                                                )}
                                            </p>
                                        </div>

                                        <span
                                            className={`rounded-full px-3 py-1 text-xs font-medium ${
                                                provider.is_available
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {provider.is_available
                                                ? 'Disponible'
                                                : 'Non disponible actuellement'}
                                        </span>
                                    </div>
                                    <span className="mt-4 text-sm font-medium text-primary">
                                        Voir le profil
                                    </span>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="mt-4 rounded-xl border border-dashed p-8 text-center">
                            <Search className="mx-auto size-8 text-muted-foreground" />

                            <h3 className="mt-3 font-semibold">
                                Aucun prestataire trouvé
                            </h3>

                            <p className="mt-2 text-sm text-muted-foreground">
                                Aucun professionnel approuvé ne propose ce
                                service pour les critères indiqués. Essayez une
                                autre commune ou un autre quartier.
                            </p>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
