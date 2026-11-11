import { Head, Link } from '@inertiajs/react';
import { ArrowRight, MapPin, Wrench } from 'lucide-react';

type Props = {
    service: { name: string; slug: string };
    zone: string;
    zoneSlug: string;
};

export default function ServiceLocation({ service, zone, zoneSlug }: Props) {
    const title = `${service.name} à ${zone}`;
    return (
        <>
            <Head title={title}>
                <meta
                    name="description"
                    content={`Recherchez ${service.name.toLowerCase()} à ${zone} avec ProxiServices. La recherche de professionnels à proximité demande l’autorisation GPS.`}
                />
                <meta name="robots" content="index,follow" />
                <link
                    rel="canonical"
                    href={`/services/${service.slug}/a/${zoneSlug}`}
                />
                <meta property="og:type" content="website" />
                <meta
                    property="og:title"
                    content={`${title} · ProxiServices`}
                />
                <meta
                    property="og:description"
                    content={`Recherchez un professionnel pour ${service.name.toLowerCase()} à ${zone}.`}
                />
            </Head>
            <main className="min-h-dvh bg-[#f5faf9] px-5 py-8 text-slate-950 sm:px-8 dark:bg-[#0d1f23] dark:text-slate-50">
                <div className="mx-auto max-w-4xl">
                    <Link
                        href="/"
                        className="inline-flex min-h-11 items-center gap-2 font-bold text-teal-900 dark:text-teal-200"
                    >
                        <Wrench size={18} />
                        ProxiServices
                    </Link>
                    <p className="mt-12 inline-flex items-center gap-2 rounded-full bg-teal-100 px-3 py-2 text-sm font-semibold text-teal-950">
                        <MapPin size={16} />
                        {zone}
                    </p>
                    <h1 className="mt-4 max-w-3xl text-4xl font-extrabold tracking-tight sm:text-5xl">
                        {service.name} à {zone}
                    </h1>
                    <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                        Consultez les professionnels qui proposent ce service
                        dans votre secteur. Pour classer les résultats par
                        proximité, la plateforme demande votre accord GPS après
                        connexion.
                    </p>
                    <Link
                        href={`/client/services/${service.slug}/providers`}
                        className="mt-7 inline-flex min-h-12 items-center gap-2 rounded-xl bg-teal-800 px-5 font-bold text-white hover:bg-teal-900"
                    >
                        Rechercher avec ma position <ArrowRight size={18} />
                    </Link>
                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                        La recherche est bloquée si la géolocalisation est
                        refusée ou indisponible; aucun choix manuel ne remplace
                        le GPS.
                    </p>
                </div>
            </main>
        </>
    );
}
