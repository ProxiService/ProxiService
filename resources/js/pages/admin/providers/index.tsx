import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BriefcaseBusiness,
    CheckCircle2,
    Clock,
    Mail,
    MapPin,
    Phone,
    XCircle,
} from 'lucide-react';

type ProviderProfile = {
    id: number;
    business_name: string | null;
    bio: string | null;
    phone: string | null;
    years_experience: number | null;
    city: string | null;
    neighborhood: string | null;
    address: string | null;
    status: string;
    created_at: string | null;
    user: {
        id: number;
        name: string;
        email: string;
    };
    services: {
        id: number;
        name: string;
    }[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    profiles: {
        data: ProviderProfile[];
        current_page: number;
        last_page: number;
        links: PaginationLink[];
    };
};

export default function PendingProviders({ profiles }: Props) {
    function approve(profileId: number) {
        if (
            window.confirm(
                'Confirmer la validation de ce profil professionnel ?',
            )
        ) {
            router.patch(
                `/admin/providers/${profileId}/approve`,
                {},
                { preserveScroll: true },
            );
        }
    }

    function reject(profileId: number) {
        if (window.confirm('Confirmer le rejet de ce profil professionnel ?')) {
            router.patch(
                `/admin/providers/${profileId}/reject`,
                {},
                { preserveScroll: true },
            );
        }
    }

    return (
        <>
            <Head title="Validation des prestataires" />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                <Link
                    href="/admin/dashboard"
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour à l'administration
                </Link>

                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Administration
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        Validation des prestataires
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Examinez les profils professionnels avant de les
                        autoriser à apparaître dans les résultats de recherche.
                    </p>

                    <div className="mt-4 inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm">
                        <Clock className="size-4 text-primary" />
                        {profiles.data.length} profil(s) sur cette page
                    </div>
                </header>

                {profiles.data.length > 0 ? (
                    <section className="flex flex-col gap-5">
                        {profiles.data.map((profile) => (
                            <article
                                key={profile.id}
                                className="rounded-xl border bg-card p-5 md:p-6"
                            >
                                <div className="flex flex-col gap-5">
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h2 className="text-xl font-semibold">
                                                {profile.business_name ||
                                                    profile.user.name}
                                            </h2>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Compte : {profile.user.name}
                                            </p>

                                            {profile.created_at && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Profil créé le{' '}
                                                    {profile.created_at}
                                                </p>
                                            )}
                                        </div>

                                        <span className="inline-flex w-fit items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
                                            <Clock className="size-3" />
                                            En attente
                                        </span>
                                    </div>

                                    {profile.bio && (
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            {profile.bio}
                                        </p>
                                    )}

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="flex items-start gap-2 text-sm">
                                            <Mail className="mt-0.5 size-4 text-muted-foreground" />
                                            <span>{profile.user.email}</span>
                                        </div>

                                        {profile.phone && (
                                            <div className="flex items-start gap-2 text-sm">
                                                <Phone className="mt-0.5 size-4 text-muted-foreground" />
                                                <span>{profile.phone}</span>
                                            </div>
                                        )}

                                        <div className="flex items-start gap-2 text-sm">
                                            <MapPin className="mt-0.5 size-4 text-muted-foreground" />
                                            <span>
                                                {[
                                                    profile.city,
                                                    profile.neighborhood,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') ||
                                                    'Zone non renseignée'}
                                            </span>
                                        </div>

                                        {profile.years_experience !== null && (
                                            <div className="flex items-start gap-2 text-sm">
                                                <BriefcaseBusiness className="mt-0.5 size-4 text-muted-foreground" />
                                                <span>
                                                    {profile.years_experience}{' '}
                                                    année(s) d'expérience
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    <div>
                                        <h3 className="text-sm font-semibold">
                                            Services proposés
                                        </h3>

                                        {profile.services.length > 0 ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {profile.services.map(
                                                    (service) => (
                                                        <span
                                                            key={service.id}
                                                            className="rounded-full border px-3 py-1 text-xs"
                                                        >
                                                            {service.name}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                Aucun service sélectionné.
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                                        <button
                                            type="button"
                                            onClick={() => reject(profile.id)}
                                            className="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-accent"
                                        >
                                            <XCircle className="size-4" />
                                            Rejeter
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => approve(profile.id)}
                                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                                        >
                                            <CheckCircle2 className="size-4" />
                                            Approuver
                                        </button>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </section>
                ) : (
                    <section className="rounded-xl border border-dashed p-10 text-center">
                        <CheckCircle2 className="mx-auto size-10 text-muted-foreground" />

                        <h2 className="mt-3 text-lg font-semibold">
                            Aucun profil en attente
                        </h2>

                        <p className="mt-2 text-sm text-muted-foreground">
                            Tous les profils professionnels ont été examinés ou
                            aucun prestataire ne s'est encore inscrit.
                        </p>
                    </section>
                )}

                {profiles.last_page > 1 && (
                    <nav
                        aria-label="Pagination des prestataires"
                        className="flex flex-wrap justify-center gap-2"
                    >
                        {profiles.links.map((link, index) => (
                            <span key={`${link.label}-${index}`}>
                                {link.url ? (
                                    <Link
                                        href={link.url}
                                        preserveScroll
                                        className={`inline-block rounded-lg border px-3 py-2 text-sm ${
                                            link.active
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-accent'
                                        }`}
                                    >
                                        {link.label
                                            .replace('&laquo;', '«')
                                            .replace('&raquo;', '»')}
                                    </Link>
                                ) : (
                                    <span className="inline-block rounded-lg border px-3 py-2 text-sm text-muted-foreground opacity-50">
                                        {link.label
                                            .replace('&laquo;', '«')
                                            .replace('&raquo;', '»')}
                                    </span>
                                )}
                            </span>
                        ))}
                    </nav>
                )}
            </div>
        </>
    );
}
