import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    BriefcaseBusiness,
    ChartNoAxesCombined,
    CircleDollarSign,
    Clock3,
    Wallet,
} from 'lucide-react';
import { dashboard as adminDashboard } from '@/routes/admin';

type CommissionItem = {
    id: number;
    service_request_id: number;
    service_name: string;
    client_name: string;
    provider_name: string;
    gross_amount: number;
    commission_rate: number;
    commission_amount: number;
    provider_amount: number;
    collection_status: string;
    collection_status_label: string;
    is_reversed: boolean;
    reversed_at: string | null;
    reversal_reason: string | null;
    calculated_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedCommissions = {
    data: CommissionItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type FinanceSummary = {
    total_commissions: number;
    gross_total: number;
    commission_total: number;
    provider_total: number;
    pending_total: number;
    reversed_total: number;
    reversed_gross_total: number;
    reversed_count: number;
};

type PageProps = {
    summary: FinanceSummary;
    commissions: PaginatedCommissions;
};

function formatMoney(amount: number): string {
    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 0,
    }).format(amount)} FCFA`;
}

function formatRate(rate: number): string {
    return `${new Intl.NumberFormat('fr-FR', {
        maximumFractionDigits: 2,
    }).format(rate)} %`;
}

function getStatusClass(status: string): string {
    switch (status) {
        case 'pending':
            return 'bg-amber-100 text-amber-800';
        case 'collected':
            return 'bg-emerald-100 text-emerald-800';
        case 'paid':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-700';
    }
}

function Pagination({ commissions }: { commissions: PaginatedCommissions }) {
    if (commissions.last_page <= 1) {
        return null;
    }

    return (
        <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-600">
                Page {commissions.current_page} sur {commissions.last_page}
            </p>

            <nav
                aria-label="Pagination des commissions"
                className="flex flex-wrap items-center gap-1"
            >
                {commissions.links.map((link, index) => {
                    const label = link.label
                        .replace('&laquo;', '«')
                        .replace('&raquo;', '»');
                    const className = [
                        'rounded-lg px-3 py-2 text-sm font-medium',
                        link.active
                            ? 'bg-indigo-600 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-100',
                        !link.url ? 'cursor-not-allowed opacity-40' : '',
                    ].join(' ');

                    return link.url ? (
                        <Link
                            key={`${label}-${index}`}
                            href={link.url}
                            preserveScroll
                            aria-current={link.active ? 'page' : undefined}
                            className={className}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span key={`${label}-${index}`} className={className}>
                            {label}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}

export default function AdminCommissionsIndex({
    summary,
    commissions,
}: PageProps) {
    return (
        <>
            <Head title="Commissions et finances" />

            <main className="min-h-screen bg-gray-50 px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <header className="mb-8">
                        <Link
                            href={adminDashboard()}
                            className="mb-4 inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            <ArrowLeft className="size-4" />
                            Retour au tableau de bord
                        </Link>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                                    Commissions et finances
                                </h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-600 sm:text-base">
                                    Consulte les prix convenus, les commissions
                                    calculées et les montants attribués aux
                                    prestataires.
                                </p>
                            </div>

                            <div className="inline-flex w-fit items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                                <BriefcaseBusiness className="size-5 text-indigo-600" />
                                <div>
                                    <p className="text-xs text-gray-500">
                                        Prestations enregistrées
                                    </p>
                                    <p className="text-lg font-bold text-gray-900">
                                        {summary.total_commissions}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </header>

                    <section
                        aria-label="Résumé financier"
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                    >
                        <article className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-medium text-gray-600">
                                    Montant des prestations
                                </span>
                                <div className="flex size-10 items-center justify-center rounded-xl bg-gray-100">
                                    <Banknote className="size-5 text-gray-700" />
                                </div>
                            </div>
                            <p className="mt-4 text-xl font-bold text-gray-900 sm:text-2xl">
                                {formatMoney(summary.gross_total)}
                            </p>
                            <p className="mt-2 text-xs leading-5 text-gray-500">
                                Somme des prix enregistrés pour les prestations
                                confirmées comme terminées.
                            </p>
                        </article>

                        <article className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-medium text-gray-600">
                                    Commissions calculées
                                </span>
                                <div className="flex size-10 items-center justify-center rounded-xl bg-indigo-50">
                                    <ChartNoAxesCombined className="size-5 text-indigo-600" />
                                </div>
                            </div>
                            <p className="mt-4 text-xl font-bold text-indigo-700 sm:text-2xl">
                                {formatMoney(summary.commission_total)}
                            </p>
                            <p className="mt-2 text-xs leading-5 text-gray-500">
                                Total théorique des commissions de la
                                plateforme.
                            </p>
                        </article>

                        <article className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-medium text-gray-600">
                                    Part des prestataires
                                </span>
                                <div className="flex size-10 items-center justify-center rounded-xl bg-emerald-50">
                                    <Wallet className="size-5 text-emerald-600" />
                                </div>
                            </div>
                            <p className="mt-4 text-xl font-bold text-emerald-700 sm:text-2xl">
                                {formatMoney(summary.provider_total)}
                            </p>
                            <p className="mt-2 text-xs leading-5 text-gray-500">
                                Montant attribué aux prestataires avant leurs
                                autres frais éventuels.
                            </p>
                        </article>

                        <article className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-medium text-gray-600">
                                    Commissions à collecter
                                </span>
                                <div className="flex size-10 items-center justify-center rounded-xl bg-amber-50">
                                    <Clock3 className="size-5 text-amber-600" />
                                </div>
                            </div>
                            <p className="mt-4 text-xl font-bold text-amber-700 sm:text-2xl">
                                {formatMoney(summary.pending_total)}
                            </p>
                            <p className="mt-2 text-xs leading-5 text-gray-500">
                                Commissions dont la collecte n'est pas
                                enregistrée.
                            </p>
                        </article>

                        <article className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-medium text-gray-600">
                                    Commissions neutralisées
                                </span>

                                <div className="flex size-10 items-center justify-center rounded-xl bg-red-50">
                                    <CircleDollarSign className="size-5 text-red-600" />
                                </div>
                            </div>

                            <p className="mt-4 text-xl font-bold text-red-700 sm:text-2xl">
                                {formatMoney(summary.reversed_total)}
                            </p>

                            <p className="mt-2 text-xs leading-5 text-gray-500">
                                {summary.reversed_count} commission
                                {summary.reversed_count > 1 ? 's' : ''}{' '}
                                neutralisée
                                {summary.reversed_count > 1 ? 's' : ''} après
                                remboursement.
                            </p>

                            <p className="mt-2 text-xs text-gray-500">
                                Volume remboursé:{' '}
                                {formatMoney(summary.reversed_gross_total)}
                            </p>
                        </article>
                    </section>

                    <section className="mt-8 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="flex flex-col gap-2 border-b border-gray-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div>
                                <h2 className="text-lg font-bold text-gray-900">
                                    Détail des commissions
                                </h2>
                                <p className="mt-1 text-sm text-gray-500">
                                    {commissions.total} enregistrement
                                    {commissions.total > 1 ? 's' : ''}
                                </p>
                            </div>
                        </div>

                        {commissions.data.length === 0 ? (
                            <div className="px-5 py-14 text-center">
                                <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-gray-100">
                                    <CircleDollarSign className="size-7 text-gray-500" />
                                </div>
                                <h3 className="mt-4 text-base font-semibold text-gray-900">
                                    Aucune commission enregistrée
                                </h3>
                                <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600">
                                    Les enregistrements apparaîtront ici lorsque
                                    les clients confirmeront des prestations
                                    terminées.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[1000px] border-collapse text-left text-sm">
                                        <thead className="bg-gray-50 text-xs tracking-wide text-gray-500 uppercase">
                                            <tr>
                                                <th className="px-5 py-4 font-semibold">
                                                    Prestation
                                                </th>
                                                <th className="px-5 py-4 font-semibold">
                                                    Participants
                                                </th>
                                                <th className="px-5 py-4 font-semibold">
                                                    Prix convenu
                                                </th>
                                                <th className="px-5 py-4 font-semibold">
                                                    Taux
                                                </th>
                                                <th className="px-5 py-4 font-semibold">
                                                    Commission
                                                </th>
                                                <th className="px-5 py-4 font-semibold">
                                                    Part prestataire
                                                </th>
                                                <th className="px-5 py-4 font-semibold">
                                                    Collecte
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {commissions.data.map(
                                                (commission) => (
                                                    <tr
                                                        key={commission.id}
                                                        className="align-top transition hover:bg-gray-50"
                                                    >
                                                        <td className="px-5 py-4">
                                                            <p className="font-semibold text-gray-900">
                                                                {
                                                                    commission.service_name
                                                                }
                                                            </p>
                                                            <p className="mt-1 text-xs text-gray-500">
                                                                Demande n°
                                                                {
                                                                    commission.service_request_id
                                                                }
                                                            </p>
                                                            {commission.calculated_at && (
                                                                <p className="mt-1 text-xs text-gray-400">
                                                                    {
                                                                        commission.calculated_at
                                                                    }
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4">
                                                            <p className="font-medium text-gray-900">
                                                                {
                                                                    commission.client_name
                                                                }
                                                            </p>
                                                            <p className="mt-1 text-xs text-gray-500">
                                                                Prestataire :{' '}
                                                                {
                                                                    commission.provider_name
                                                                }
                                                            </p>
                                                        </td>
                                                        <td className="px-5 py-4 font-medium whitespace-nowrap text-gray-900">
                                                            {formatMoney(
                                                                commission.gross_amount,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4 whitespace-nowrap text-gray-700">
                                                            {formatRate(
                                                                commission.commission_rate,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4 font-semibold whitespace-nowrap text-indigo-700">
                                                            {formatMoney(
                                                                commission.commission_amount,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4 font-medium whitespace-nowrap text-emerald-700">
                                                            {formatMoney(
                                                                commission.provider_amount,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4">
                                                            <span
                                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap ${getStatusClass(commission.collection_status)}`}
                                                            >
                                                                {
                                                                    commission.collection_status_label
                                                                }
                                                            </span>
                                                            {commission.is_reversed && (
                                                                <div className="mt-2 max-w-48 text-xs leading-5 text-gray-500">
                                                                    {commission.reversal_reason ||
                                                                        'Commission neutralisée.'}

                                                                    {commission.reversed_at && (
                                                                        <p className="mt-1">
                                                                            Le{' '}
                                                                            {
                                                                                commission.reversed_at
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="border-t border-gray-100 px-5 py-4 sm:px-6">
                                    <Pagination commissions={commissions} />
                                </div>
                            </>
                        )}
                    </section>

                    <aside className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p className="text-sm leading-6 text-amber-900">
                            <strong>À retenir :</strong> ces montants sont
                            calculés à partir des prix convenus. Une commission
                            indiquée « À collecter » n'est pas un paiement
                            encaissé. Le règlement réel nécessitera une
                            intégration de paiement et une confirmation de
                            transaction.
                        </p>
                    </aside>
                </div>
            </main>
        </>
    );
}
