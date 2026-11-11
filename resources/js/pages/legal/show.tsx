import { Head } from '@inertiajs/react';
import { ArrowLeft, CircleAlert } from 'lucide-react';

type PageProps = { slug: string; title: string; sections: string[] };

export default function LegalPage({ title, sections }: PageProps) {
    return (
        <>
            <Head title={title}>
                <meta name="robots" content="noindex,follow" />
                <meta
                    name="description"
                    content={`${title} — informations de ProxiServices à compléter par l’entité éditrice.`}
                />
            </Head>
            <main className="mx-auto min-h-dvh max-w-3xl px-5 py-8 sm:px-8">
                <a
                    href="/"
                    className="inline-flex min-h-11 items-center gap-2 font-semibold text-teal-900"
                >
                    <ArrowLeft size={18} />
                    Accueil
                </a>
                <header className="mt-8">
                    <p className="text-sm font-semibold text-teal-800">
                        Informations de la plateforme
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">{title}</h1>
                    <p className="mt-2 text-sm text-slate-600">
                        Document préparatoire · version à compléter
                    </p>
                </header>
                <div className="mt-6 flex gap-3 rounded-2xl border border-orange-300 bg-orange-50 p-4 text-orange-950">
                    <CircleAlert className="mt-0.5 size-5 shrink-0" />
                    <p className="text-sm leading-6">
                        <strong>Contenu à valider avant publication.</strong>{' '}
                        Les coordonnées de l’entité, ses pratiques réelles et
                        les informations juridiques applicables ne nous ont pas
                        été communiquées. Cette page ne constitue pas un
                        document final ni une déclaration de conformité.
                    </p>
                </div>
                <div className="mt-8 space-y-7">
                    {sections.map((section, index) => (
                        <section key={section}>
                            <h2 className="text-xl font-semibold">
                                {index + 1}. {section}
                            </h2>
                            <div className="mt-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-slate-700">
                                <p className="font-semibold">
                                    [À COMPLÉTER PAR L’ENTITÉ ÉDITRICE]
                                </p>
                                <p className="mt-2 text-sm leading-6">
                                    Renseigner les informations exactes
                                    relatives à « {section.toLowerCase()} »,
                                    puis faire valider le texte et les
                                    procédures applicables avant toute
                                    publication.
                                </p>
                            </div>
                        </section>
                    ))}
                </div>
            </main>
        </>
    );
}
