import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Clock3,
    MapPin,
    ShieldCheck,
    Wrench,
} from 'lucide-react';

const categories = [
    'Plomberie',
    'Électricité',
    'Menuiserie',
    'Peinture',
    'Serrurerie',
    'Ménage',
];

export default function Welcome() {
    const { auth } = usePage().props;
    const destination = auth.user ? '/client/dashboard' : '/login';
    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: 'ProxiServices',
        description:
            'Plateforme de mise en relation entre clients et professionnels de proximité à Abidjan.',
        inLanguage: 'fr-CI',
    };

    return (
        <>
            <Head title="Des artisans près de vous à Abidjan">
                <meta
                    name="description"
                    content="Trouvez un artisan à Abidjan, comparez les devis et suivez votre demande sur ProxiServices."
                />
                <meta name="robots" content="index,follow" />
                <link rel="canonical" href="/" />
                <meta property="og:type" content="website" />
                <meta
                    property="og:title"
                    content="ProxiServices — les bons artisans près de vous"
                />
                <meta
                    property="og:description"
                    content="Trouvez un professionnel de proximité à Abidjan."
                />
                <meta property="og:url" content="/" />
                <script
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{
                        __html: JSON.stringify(structuredData),
                    }}
                />
            </Head>
            <main className="min-h-dvh overflow-hidden bg-[#f5faf9] text-slate-950 dark:bg-[#0d1f23] dark:text-slate-50">
                <header className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
                    <a
                        href="/"
                        className="flex min-h-11 items-center gap-2 font-bold tracking-tight text-teal-900 dark:text-teal-200"
                    >
                        <span className="grid size-9 place-items-center rounded-xl bg-teal-800 text-white">
                            <Wrench size={19} />
                        </span>
                        ProxiServices
                    </a>
                    <nav
                        aria-label="Navigation principale"
                        className="flex items-center gap-2"
                    >
                        <Link
                            href={auth.user ? '/client/dashboard' : '/login'}
                            className="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-teal-900 hover:bg-teal-50 dark:text-teal-200 dark:hover:bg-teal-950"
                        >
                            {auth.user ? 'Mon espace' : 'Connexion'}
                        </Link>
                        {!auth.user && (
                            <Link
                                href="/register"
                                className="inline-flex min-h-11 items-center rounded-xl bg-teal-800 px-4 text-sm font-semibold text-white hover:bg-teal-900"
                            >
                                S’inscrire
                            </Link>
                        )}
                    </nav>
                </header>
                <section className="mx-auto grid max-w-7xl items-center gap-10 px-5 pt-8 pb-16 sm:px-8 md:grid-cols-[1.05fr_.95fr] md:py-20">
                    <div>
                        <p className="mb-4 inline-flex items-center gap-2 rounded-full bg-teal-100 px-3 py-1.5 text-sm font-semibold text-teal-900">
                            <MapPin size={16} /> Abidjan et ses environs
                        </p>
                        <h1 className="max-w-2xl text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                            Un artisan fiable,{' '}
                            <span className="text-teal-800 dark:text-teal-200">
                                au bon moment.
                            </span>
                        </h1>
                        <p className="mt-5 max-w-xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                            Décrivez votre besoin, trouvez un professionnel
                            proche et échangez sur votre intervention en toute
                            simplicité.
                        </p>
                        <Link
                            href="/client/requests/urgent"
                            className="mt-4 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-orange-600 px-5 font-bold text-white shadow-sm hover:bg-orange-700 sm:w-auto"
                        >
                            <Clock3 size={19} /> Urgence : trouver de l’aide{' '}
                            <ArrowRight size={17} />
                        </Link>
                        <p className="mt-2 text-xs text-slate-600 dark:text-slate-300">
                            La demande urgente requiert votre accord et votre
                            position GPS pour rechercher à proximité.
                        </p>
                        <form
                            action="/client/search"
                            method="get"
                            className="mt-8 grid gap-3 rounded-2xl border border-teal-100 bg-white p-3 shadow-lg shadow-teal-950/5 sm:grid-cols-[1fr_auto] dark:border-slate-700 dark:bg-slate-900"
                        >
                            <label className="flex min-h-12 items-center gap-3 rounded-xl bg-slate-50 px-3 dark:bg-slate-800">
                                <Wrench
                                    size={18}
                                    className="text-teal-800 dark:text-teal-200"
                                />
                                <span className="sr-only">
                                    Service recherché
                                </span>
                                <input
                                    name="q"
                                    placeholder="Quel service ?"
                                    className="min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-slate-500 dark:placeholder:text-slate-300"
                                />
                            </label>
                            <button
                                className="min-h-12 rounded-xl bg-teal-800 px-5 font-bold text-white hover:bg-teal-900"
                                type="submit"
                            >
                                Rechercher
                            </button>
                        </form>
                    </div>
                    <div className="relative mx-auto w-full max-w-lg rounded-[2rem] bg-gradient-to-br from-teal-800 to-cyan-700 p-6 text-white shadow-2xl shadow-teal-900/20 sm:p-9">
                        <div
                            aria-hidden="true"
                            className="absolute -top-8 -right-8 size-36 rounded-full border-[24px] border-white/10"
                        />
                        <p className="text-sm font-semibold text-teal-100">
                            Votre quotidien, plus simple
                        </p>
                        <h2 className="mt-2 text-3xl font-bold">
                            De la recherche à l’intervention.
                        </h2>
                        <div className="mt-8 space-y-4">
                            {[
                                ['01', 'Décrivez votre besoin'],
                                ['02', 'Échangez avec un artisan'],
                                ['03', 'Convenez du prix et du service'],
                            ].map(([n, t]) => (
                                <div
                                    key={n}
                                    className="flex items-center gap-4 rounded-2xl bg-white/10 p-4"
                                >
                                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white font-bold text-teal-900">
                                        {n}
                                    </span>
                                    <span className="font-semibold">{t}</span>
                                </div>
                            ))}
                        </div>
                        <div className="mt-6 flex items-center gap-2 text-sm text-teal-50">
                            <BadgeCheck size={18} /> Suivi de vos échanges
                            depuis la plateforme
                        </div>
                    </div>
                </section>
                <section className="bg-white px-5 py-14 sm:px-8 dark:bg-slate-900">
                    <div className="mx-auto max-w-7xl">
                        <p className="text-sm font-bold tracking-wide text-teal-800 uppercase dark:text-teal-300">
                            Les métiers du quotidien
                        </p>
                        <h2 className="mt-2 text-3xl font-bold">
                            De quel professionnel avez-vous besoin ?
                        </h2>
                        <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            {categories.map((c) => (
                                <Link
                                    key={c}
                                    href={destination}
                                    className="flex min-h-16 items-center gap-3 rounded-2xl border border-slate-200 p-4 font-semibold hover:border-teal-600 hover:bg-teal-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-teal-950"
                                >
                                    <Wrench
                                        size={18}
                                        className="shrink-0 text-teal-800 dark:text-teal-200"
                                    />
                                    {c}
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
                <section className="mx-auto grid max-w-7xl gap-8 px-5 py-14 sm:px-8 md:grid-cols-2">
                    <div>
                        <p className="text-sm font-bold tracking-wide text-teal-800 uppercase dark:text-teal-300">
                            Pour les imprévus
                        </p>
                        <h2 className="mt-2 text-3xl font-bold">
                            Un problème urgent ? Lancez une demande rapidement.
                        </h2>
                        <p className="mt-3 leading-7 text-slate-600 dark:text-slate-300">
                            Avec votre accord GPS, la plateforme recherche des
                            prestataires disponibles à proximité. Votre position
                            précise reste privée.
                        </p>
                        <Link
                            href="/client/requests/urgent"
                            className="mt-5 inline-flex min-h-12 items-center gap-2 rounded-xl bg-orange-600 px-5 font-bold text-white hover:bg-orange-700"
                        >
                            Décrire une urgence <ArrowRight size={17} />
                        </Link>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {[
                            [
                                'Échanges suivis',
                                'Retrouvez vos discussions au même endroit.',
                            ],
                            [
                                'Prestataires examinés',
                                'Les profils sont soumis à une validation de la plateforme.',
                            ],
                            [
                                'Prix discuté',
                                'Échangez sur le devis avant de convenir de l’intervention.',
                            ],
                        ].map(([title, copy]) => (
                            <article
                                key={title}
                                className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                            >
                                <ShieldCheck className="text-teal-800 dark:text-teal-200" />
                                <h3 className="mt-3 font-bold">{title}</h3>
                                <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {copy}
                                </p>
                            </article>
                        ))}
                    </div>
                </section>
                <section className="bg-white px-5 py-14 sm:px-8 dark:bg-slate-900">
                    <div className="mx-auto max-w-7xl">
                        <p className="text-sm font-bold tracking-wide text-teal-800 uppercase dark:text-teal-300">
                            Avis clients
                        </p>
                        <h2 className="mt-2 text-3xl font-bold">
                            Les expériences après intervention
                        </h2>
                        <div className="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            <p className="font-semibold">
                                Les avis apparaîtront ici après une prestation
                                terminée et payée.
                            </p>
                            <p className="mt-2 text-sm leading-6">
                                Aucun témoignage n’est affiché tant que des avis
                                vérifiés ne sont pas disponibles.
                            </p>
                        </div>
                    </div>
                </section>
                <section className="mx-auto max-w-7xl px-5 py-14 sm:px-8">
                    <p className="text-sm font-bold tracking-wide text-teal-800 uppercase dark:text-teal-300">
                        Questions fréquentes
                    </p>
                    <h2 className="mt-2 text-3xl font-bold">
                        Avant de commencer
                    </h2>
                    <div className="mt-6 grid gap-3 md:grid-cols-2">
                        {[
                            [
                                'Comment trouver un professionnel proche ?',
                                'Choisissez un service puis autorisez la géolocalisation. La recherche de prestataires est bloquée si le GPS est refusé ou indisponible.',
                            ],
                            [
                                'Dois-je partager ma position précise ?',
                                'Oui, l’appareil doit transmettre une position pour calculer la proximité; celle-ci reste privée et n’est pas communiquée aux prestataires.',
                            ],
                            [
                                'Puis-je comparer plusieurs devis ?',
                                'Les devis reçus apparaissent dans le suivi de votre demande et peuvent être consultés avant votre décision.',
                            ],
                            [
                                'Le séquestre et les remboursements sont-ils disponibles ?',
                                'La plateforme ne confirme actuellement aucune capacité de retenir, rembourser ou reverser des fonds. Ces opérations restent indisponibles.',
                            ],
                        ].map(([question, answer]) => (
                            <details
                                key={question}
                                className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                            >
                                <summary className="min-h-11 cursor-pointer py-2 font-semibold">
                                    {question}
                                </summary>
                                <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {answer}
                                </p>
                            </details>
                        ))}
                    </div>
                </section>
                <section className="bg-teal-950 px-5 py-12 text-white sm:px-8">
                    <div className="mx-auto flex max-w-7xl flex-col justify-between gap-5 sm:flex-row sm:items-center">
                        <div>
                            <h2 className="text-2xl font-bold">
                                Vous êtes artisan ?
                            </h2>
                            <p className="mt-2 text-teal-100">
                                Présentez votre activité et recevez des demandes
                                de clients.
                            </p>
                        </div>
                        <Link
                            href="/register"
                            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-5 font-bold text-teal-950"
                        >
                            Rejoindre ProxiServices <ArrowRight size={17} />
                        </Link>
                    </div>
                </section>
                <footer className="bg-slate-950 px-5 py-9 text-sm text-slate-200 sm:px-8">
                    <div className="mx-auto flex max-w-7xl flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            © {new Date().getFullYear()} ProxiServices · Abidjan
                        </p>
                        <nav
                            aria-label="Informations"
                            className="flex flex-wrap gap-x-5 gap-y-2"
                        >
                            <a
                                className="underline underline-offset-4"
                                href="/informations/cgu"
                            >
                                Conditions d’utilisation
                            </a>
                            <a
                                className="underline underline-offset-4"
                                href="/informations/confidentialite"
                            >
                                Confidentialité
                            </a>
                            <a
                                className="underline underline-offset-4"
                                href="/informations/mentions-legales"
                            >
                                Mentions légales
                            </a>
                            <a
                                className="underline underline-offset-4"
                                href="/informations/paiements-remboursements"
                            >
                                Paiements et remboursements
                            </a>
                        </nav>
                    </div>
                </footer>
            </main>
        </>
    );
}
