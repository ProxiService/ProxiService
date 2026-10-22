import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ClipboardList,
    MapPin,
    Search,
    ShieldCheck,
    Wrench,
} from 'lucide-react';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
};

type ClientDashboardProps = {
    categories: Category[];
};

export default function ClientDashboard({ categories }: ClientDashboardProps) {
    return (
        <>
            <Head title="Espace client" />

            <div className="flex flex-col gap-8 p-4 md:p-8">
                {/* En-tête */}
                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Espace client
                    </p>

                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        Bonjour 👋
                    </h1>

                    <p className="mt-2 text-muted-foreground">
                        De quel service avez-vous besoin aujourd'hui ?
                    </p>
                </header>

                {/* Zone de recherche fonctionnelle */}
                <section className="rounded-2xl bg-primary p-6 text-primary-foreground shadow-sm md:p-8">
                    <div className="max-w-2xl">
                        <h2 className="text-2xl font-bold md:text-3xl">
                            Trouvez le bon artisan près de chez vous.
                        </h2>

                        <p className="mt-3 text-sm opacity-90 md:text-base">
                            Plombier, électricien, menuisier... trouvez un
                            professionnel adapté à votre besoin.
                        </p>

                        <form
                            action="/client/search"
                            method="GET"
                            className="mt-6 flex items-center gap-3 rounded-xl bg-background p-3 text-foreground"
                        >
                            <Search className="size-5 shrink-0 text-muted-foreground" />

                            <input
                                type="search"
                                name="q"
                                placeholder="Quel service recherchez-vous ?"
                                aria-label="Service recherché"
                                maxLength={100}
                                required
                                className="min-w-0 flex-1 bg-transparent text-sm outline-none"
                            />

                            <button
                                type="submit"
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90"
                            >
                                Rechercher
                            </button>
                        </form>

                        <div className="mt-4 flex items-center gap-2 text-sm">
                            <MapPin className="size-4" />

                            <span>Votre quartier ou votre commune</span>
                        </div>
                    </div>
                </section>

                {/* Catégories chargées depuis Laravel */}
                <section>
                    <div className="mb-4">
                        <h2 className="text-xl font-semibold">
                            Explorer les services
                        </h2>

                        <p className="mt-1 text-sm text-muted-foreground">
                            Choisissez le métier correspondant à votre besoin.
                        </p>
                    </div>

                    {categories.length > 0 ? (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {categories.map((category) => (
                                <Link
                                    key={category.id}
                                    href={`/client/categories/${category.slug}`}
                                    className="block rounded-xl border bg-card p-5 transition-colors hover:border-primary/50 hover:bg-accent/30"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <Wrench className="size-5" />
                                        </div>

                                        <ArrowRight className="size-5 text-muted-foreground" />
                                    </div>

                                    <h3 className="mt-4 font-semibold">
                                        {category.name}
                                    </h3>

                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {category.description ??
                                            'Découvrez les prestations disponibles dans cette catégorie.'}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed p-8 text-center">
                            <Wrench className="mx-auto size-8 text-muted-foreground" />

                            <h3 className="mt-3 font-semibold">
                                Aucune catégorie disponible
                            </h3>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Les catégories de services seront affichées ici
                                dès qu'elles seront disponibles.
                            </p>
                        </div>
                    )}
                </section>

                {/* Espace personnel */}
                <section>
                    <h2 className="text-xl font-semibold">
                        Votre espace personnel
                    </h2>

                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="rounded-xl border bg-card p-5">
                            <ClipboardList className="size-6 text-primary" />

                            <h3 className="mt-3 font-semibold">Mes demandes</h3>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Retrouvez vos demandes de services et leur
                                avancement.
                            </p>
                        </div>

                        <div className="rounded-xl border bg-card p-5">
                            <ShieldCheck className="size-6 text-primary" />

                            <h3 className="mt-3 font-semibold">
                                Des prestataires évalués
                            </h3>

                            <p className="mt-1 text-sm text-muted-foreground">
                                Consultez les profils, les tarifs et les avis
                                des professionnels.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
