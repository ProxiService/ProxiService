import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Clock3,
    CircleDollarSign,
    Star,
    Wrench,
} from 'lucide-react';
import NotificationBell from '../../components/NotificationBell';
import { edit as editProviderProfile } from '@/routes/prestataire/profile';
import { index as providerRequests } from '@/routes/prestataire/requests';

const stats = [
    {
        label: 'Nouvelles demandes',
        value: '0',
        description: 'Demandes à examiner',
        icon: ClipboardList,
    },
    {
        label: 'Prestations en cours',
        value: '0',
        description: 'Interventions actives',
        icon: Clock3,
    },
    {
        label: 'Prestations terminées',
        value: '0',
        description: 'Interventions réalisées',
        icon: CheckCircle2,
    },
    {
        label: 'Revenus du mois',
        value: '0 FCFA',
        description: 'Montant indicatif',
        icon: CircleDollarSign,
    },
];

export default function PrestataireDashboard() {
    return (
        <>
            <Head title="Espace prestataire" />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                <div className="flex justify-end">
                    <NotificationBell />
                </div>

                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Espace professionnel
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        Tableau de bord
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Gérez vos demandes, vos prestations et votre activité
                        professionnelle.
                    </p>
                </header>

                <section className="rounded-2xl bg-primary p-6 text-primary-foreground md:p-8">
                    <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="flex items-center gap-2 text-sm opacity-90">
                                <Wrench className="size-4" />
                                Espace artisan
                            </div>

                            <h2 className="mt-3 text-2xl font-bold">
                                Développez votre activité.
                            </h2>

                            <p className="mt-2 max-w-xl text-sm opacity-90">
                                Présentez vos services, recevez des demandes de
                                clients et suivez vos prestations depuis un seul
                                espace.
                            </p>
                        </div>

                        <div className="flex shrink-0 flex-col items-start gap-4 sm:items-end">
                            <div className="flex size-16 items-center justify-center rounded-2xl bg-white/15">
                                <Wrench className="size-8" />
                            </div>

                            <Link
                                href={editProviderProfile()}
                                className="inline-flex items-center gap-2 rounded-lg bg-background px-4 py-2.5 text-sm font-semibold text-foreground shadow-sm transition-colors hover:bg-background/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-primary focus-visible:outline-none"
                            >
                                Compléter mon profil
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Demandes reçues
                            </h2>

                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Consulte les demandes de tes clients et accepte
                                ou refuse celles que tu peux réaliser.
                            </p>
                        </div>

                        <Link
                            href={providerRequests()}
                            className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                        >
                            Mes demandes
                        </Link>
                    </div>
                </section>

                <section>
                    <h2 className="text-xl font-semibold">Vue d'ensemble</h2>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Vos principaux indicateurs d'activité.
                    </p>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {stats.map((stat) => {
                            const Icon = stat.icon;

                            return (
                                <div
                                    key={stat.label}
                                    className="rounded-xl border bg-card p-5"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-sm text-muted-foreground">
                                            {stat.label}
                                        </p>

                                        <Icon className="size-5 text-primary" />
                                    </div>

                                    <p className="mt-4 text-2xl font-bold">
                                        {stat.value}
                                    </p>

                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {stat.description}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-xl border bg-card p-6">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <CalendarClock className="size-5" />
                            </div>

                            <div>
                                <h2 className="font-semibold">
                                    Mes disponibilités
                                </h2>

                                <p className="text-sm text-muted-foreground">
                                    Organisez vos interventions.
                                </p>
                            </div>
                        </div>

                        <p className="mt-5 text-sm text-muted-foreground">
                            Vous pourrez bientôt définir vos horaires, vos jours
                            de travail et vos disponibilités pour les
                            interventions urgentes.
                        </p>
                    </div>

                    <div className="rounded-xl border bg-card p-6">
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Star className="size-5" />
                            </div>

                            <div>
                                <h2 className="font-semibold">Ma réputation</h2>

                                <p className="text-sm text-muted-foreground">
                                    Avis et satisfaction des clients.
                                </p>
                            </div>
                        </div>

                        <p className="mt-5 text-sm text-muted-foreground">
                            Les notes et les avis seront affichés ici après la
                            mise en place du système d'évaluation.
                        </p>
                    </div>
                </section>
            </div>
        </>
    );
}
