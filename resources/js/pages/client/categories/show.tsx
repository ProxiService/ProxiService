import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock3, HandCoins, Wrench } from 'lucide-react';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
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
};

type Props = {
    category: Category;
    services: ServiceItem[];
};

function formatPrice(price: string | number): string {
    const formatted = new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(Number(price));

    return `${formatted} FCFA`;
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

export default function CategoryServices({ category, services }: Props) {
    return (
        <>
            <Head title={`${category.name} - Services`} />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                <Link
                    href="/client/dashboard"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour au tableau de bord
                </Link>

                <header>
                    <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Wrench className="size-6" />
                    </div>

                    <p className="mt-4 text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Catalogue
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        {category.name}
                    </h1>

                    <p className="mt-2 max-w-2xl text-muted-foreground">
                        {category.description ??
                            `Découvrez les services proposés dans la catégorie ${category.name}.`}
                    </p>

                    <p className="mt-3 text-sm text-muted-foreground">
                        {services.length}{' '}
                        {services.length > 1
                            ? 'services disponibles'
                            : 'service disponible'}
                    </p>
                </header>

                {services.length > 0 ? (
                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {services.map((service) => (
                            <article
                                key={service.id}
                                className="flex flex-col rounded-xl border bg-card p-5"
                            >
                                <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Wrench className="size-5" />
                                </div>

                                <h2 className="mt-4 text-lg font-semibold">
                                    {service.name}
                                </h2>

                                <p className="mt-2 flex-1 text-sm text-muted-foreground">
                                    {service.description ??
                                        'Description à venir.'}
                                </p>

                                <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-4 text-sm">
                                    <div className="flex items-center gap-2">
                                        <HandCoins className="size-4 text-primary" />

                                        <span className="font-medium">
                                            {service.pricing_type === 'fixed' &&
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
                            </article>
                        ))}
                    </section>
                ) : (
                    <section className="rounded-xl border border-dashed p-8 text-center">
                        <Wrench className="mx-auto size-8 text-muted-foreground" />

                        <h2 className="mt-3 font-semibold">
                            Aucun service disponible
                        </h2>

                        <p className="mt-2 text-sm text-muted-foreground">
                            Cette catégorie ne propose pas encore de service
                            actif.
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
