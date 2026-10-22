import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    BriefcaseBusiness,
    CheckCircle2,
    CreditCard,
    Settings,
    ShieldCheck,
    Users,
    Wrench,
} from 'lucide-react';

const stats = [
    {
        label: 'Utilisateurs',
        value: '0',
        description: 'Comptes inscrits',
        icon: Users,
    },
    {
        label: 'Prestataires',
        value: '0',
        description: 'Profils professionnels',
        icon: Wrench,
    },
    {
        label: 'Prestations',
        value: '0',
        description: 'Demandes enregistrées',
        icon: BriefcaseBusiness,
    },
    {
        label: 'Commissions',
        value: '0 FCFA',
        description: 'Commissions enregistrées',
        icon: CreditCard,
    },
];

const managementItems = [
    {
        title: 'Utilisateurs',
        description: 'Consulter et gérer les comptes.',
        icon: Users,
    },
    {
        title: 'Prestataires',
        description: 'Examiner les profils professionnels.',
        icon: ShieldCheck,
    },
    {
        title: 'Services et catégories',
        description: 'Organiser les métiers proposés.',
        icon: Wrench,
    },
    {
        title: 'Prestations et contrats',
        description: 'Suivre les demandes et les contrats.',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Paiements et commissions',
        description: 'Consulter les transactions.',
        icon: CreditCard,
    },
    {
        title: 'Litiges et signalements',
        description: 'Examiner les problèmes signalés.',
        icon: AlertTriangle,
    },
];

export default function AdminDashboard() {
    return (
        <>
            <Head title="Administration" />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Administration
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        Tableau de bord
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        Supervisez les utilisateurs, les services et les
                        activités de la plateforme.
                    </p>
                </header>

                <section className="rounded-2xl bg-primary p-6 text-primary-foreground md:p-8">
                    <div className="flex items-start gap-4">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/15">
                            <ShieldCheck className="size-6" />
                        </div>

                        <div>
                            <h2 className="text-2xl font-bold">
                                Administration de ProxiServices
                            </h2>

                            <p className="mt-2 max-w-2xl text-sm opacity-90">
                                Gérez les comptes, contrôlez les prestataires et
                                assurez le bon fonctionnement de la marketplace.
                            </p>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 className="text-xl font-semibold">Vue d'ensemble</h2>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Indicateurs de démonstration, en attente de connexion
                        aux données réelles.
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

                <section>
                    <h2 className="text-xl font-semibold">
                        Gestion de la plateforme
                    </h2>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Les modules que nous développerons progressivement.
                    </p>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {managementItems.map((item) => {
                            const Icon = item.icon;

                            return (
                                <div
                                    key={item.title}
                                    className="rounded-xl border bg-card p-5"
                                >
                                    <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <Icon className="size-5" />
                                    </div>

                                    <h3 className="mt-4 font-semibold">
                                        {item.title}
                                    </h3>

                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <section className="rounded-xl border bg-card p-5">
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <Settings className="size-5" />
                        </div>

                        <div>
                            <h2 className="font-semibold">Prochaine étape</h2>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Nous connecterons ces indicateurs à la base de
                                données, puis nous développerons les écrans de
                                gestion des utilisateurs et des prestataires.
                            </p>
                        </div>
                    </div>
                </section>

                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <CheckCircle2 className="size-4" />
                    Interface d'administration initiale
                </div>
            </div>
        </>
    );
}
