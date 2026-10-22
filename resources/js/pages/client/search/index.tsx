import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Clock3,
    HandCoins,
    Search,
    Wrench,
} from 'lucide-react';

type Category = {
    id: number;
    name: string;
    slug: string;
};

type ServiceItem = {
    id: number;
    category_id: number;
    name: string;
    slug: string;
    description: string | null;
    pricing_type: 'fixed' | 'quote';
    base_price: string | number | null;
    estimated_duration_minutes: number | null;
    category: Category;
};

type SearchProps = {
    searchTerm: string;
    services: ServiceItem[];
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

export default function SearchResults({ searchTerm, services }: SearchProps) {
    return (
        <>
            <Head title="Recherche de services" />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                <Link
                    href="/client/dashboard"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour au tableau de bord
                </Link>

                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Recherche
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        Trouvez un service
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Découvrez les prestations disponibles sur la plateforme.
                    </p>

                    <form
                        action="/client/search"
                        method="GET"
                        className="mt-6 flex max-w-2xl items-center gap-3 rounded-xl border bg-background p-3"
                    >
                        <Search className="size-5 shrink-0 text-muted-foreground" />

                        <input
                            type="search"
                            name="q"
                            defaultValue={searchTerm}
                            maxLength={100}
                            placeholder="Ex. : robinet, plomberie, table..."
                            aria-label="Rechercher un service"
                            className="min-w-0 flex-1 bg-transparent text-sm outline-none"
                            required
                        />

                        <button
                            type="submit"
                            className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                        >
                            Rechercher
                        </button>
                    </form>
                </header>

                {searchTerm === '' ? (
                    <section className="rounded-xl border border-dashed p-8 text-center">
                        <Search className="mx-auto size-8 text-muted-foreground" />

                        <h2 className="mt-3 font-semibold">
                            Que recherchez-vous ?
                        </h2>

                        <p className="mt-2 text-sm text-muted-foreground">
                            Saisissez le nom d'un service ou d'un métier pour
                            découvrir les prestations correspondantes.
                        </p>
                    </section>
                ) : (
                    <section>
                        <h2 className="text-xl font-semibold">
                            Résultats pour « {searchTerm} »
                        </h2>

                        <p className="mt-1 text-sm text-muted-foreground">
                            {services.length} prestation(s) trouvée(s).
                        </p>

                        {services.length > 0 ? (
                            <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {services.map((service) => (
                                    <article
                                        key={service.id}
                                        className="flex flex-col rounded-xl border bg-card p-5"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                <Wrench className="size-5" />
                                            </div>

                                            <span className="rounded-full bg-muted px-3 py-1 text-xs">
                                                {service.category.name}
                                            </span>
                                        </div>

                                        <h3 className="mt-4 text-lg font-semibold">
                                            {service.name}
                                        </h3>

                                        <p className="mt-2 flex-1 text-sm text-muted-foreground">
                                            {service.description ??
                                                'Description à venir.'}
                                        </p>

                                        <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-4 text-sm">
                                            <div className="flex items-center gap-2">
                                                <HandCoins className="size-4 text-primary" />

                                                <span className="font-medium">
                                                    {service.pricing_type ===
                                                        'fixed' &&
                                                    service.base_price !== null
                                                        ? formatPrice(
                                                              service.base_price,
                                                          )
                                                        : 'Sur devis'}
                                                </span>
                                            </div>

                                            {service.estimated_duration_minutes !==
                                                null && (
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <Clock3 className="size-4" />

                                                    <span>
                                                        {formatDuration(
                                                            service.estimated_duration_minutes,
                                                        )}
                                                    </span>
                                                </div>
                                            )}
                                        </div>

                                        <Link
                                            href={`/client/categories/${service.category.slug}`}
                                            className="mt-5 inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-accent"
                                        >
                                            Voir la catégorie
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </article>
                                ))}
                            </div>
                        ) : (
                            <div className="mt-4 rounded-xl border border-dashed p-8 text-center">
                                <Search className="mx-auto size-8 text-muted-foreground" />

                                <h3 className="mt-3 font-semibold">
                                    Aucun résultat trouvé
                                </h3>

                                <p className="mt-2 text-sm text-muted-foreground">
                                    Essayez un autre mot ou recherchez par
                                    métier, par exemple « plomberie ».
                                </p>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
