import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock3,
    EyeOff,
    Flag,
    Star,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dismiss, hideReview } from '@/routes/admin/review-reports';

type ReviewReport = {
    id: number;
    reason: string;
    reason_label: string;
    details: string | null;
    created_at: string | null;
    reporter: {
        name: string;
    };
    review: {
        id: number;
        rating: number;
        comment: string | null;
        client_name: string;
        provider_name: string;
        service_name: string;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    reports: {
        data: ReviewReport[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
};

export default function ReviewReportsIndex({ reports }: Props) {
    const [processingId, setProcessingId] = useState<number | null>(null);

    function dismissReport(reportId: number) {
        if (
            window.confirm('Rejeter ce signalement ? L’avis restera visible.')
        ) {
            setProcessingId(reportId);
            router.patch(
                dismiss.url(reportId),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => setProcessingId(null),
                },
            );
        }
    }

    function hideReportedReview(reportId: number) {
        if (
            window.confirm(
                'Masquer cet avis ? Il ne sera plus visible publiquement et ne comptera plus dans la moyenne.',
            )
        ) {
            setProcessingId(reportId);
            router.patch(
                hideReview.url(reportId),
                {},
                {
                    preserveScroll: true,
                    onFinish: () => setProcessingId(null),
                },
            );
        }
    }

    return (
        <>
            <Head title="Signalements d’avis" />

            <main className="flex flex-col gap-8 p-4 md:p-8">
                <Link
                    href={adminDashboard()}
                    className="inline-flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Retour à l’administration
                </Link>

                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        ProxiServices · Administration
                    </p>

                    <div className="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">
                                Modération des avis
                            </h1>

                            <p className="mt-2 text-muted-foreground">
                                Examine les avis signalés par les utilisateurs
                                et prends une décision.
                            </p>
                        </div>

                        <div className="flex items-center gap-3 rounded-xl border bg-card px-4 py-3">
                            <Flag className="size-5 text-amber-600" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Signalements en attente
                                </p>
                                <p className="text-xl font-bold">
                                    {reports.total}
                                </p>
                            </div>
                        </div>
                    </div>
                </header>

                {reports.data.length > 0 ? (
                    <section className="flex flex-col gap-5">
                        {reports.data.map((report) => (
                            <article
                                key={report.id}
                                className="rounded-xl border bg-card p-5 md:p-6"
                            >
                                <div className="flex flex-col gap-5">
                                    <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                                <AlertTriangle className="size-5 text-amber-600" />
                                                {report.reason_label}
                                            </h2>

                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Signalé par{' '}
                                                {report.reporter.name}
                                                {report.created_at
                                                    ? ` · ${report.created_at}`
                                                    : ''}
                                            </p>
                                        </div>

                                        <span className="inline-flex w-fit items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
                                            <Clock3 className="size-3" />
                                            En attente
                                        </span>
                                    </header>

                                    <div>
                                        <h3 className="text-sm font-semibold">
                                            Motif et explications du signalement
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                            {report.details ||
                                                'Aucune explication supplémentaire.'}
                                        </p>
                                    </div>

                                    <section className="rounded-lg border p-4">
                                        <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h3 className="font-semibold">
                                                    Avis de{' '}
                                                    {report.review.client_name}
                                                </h3>

                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {report.review.service_name}
                                                    {' · '}
                                                    {
                                                        report.review
                                                            .provider_name
                                                    }
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            className="mt-3 flex items-center gap-1"
                                            aria-label={`Note de ${report.review.rating} sur 5`}
                                        >
                                            {[1, 2, 3, 4, 5].map((value) => (
                                                <Star
                                                    key={value}
                                                    className={`size-4 ${
                                                        value <=
                                                        report.review.rating
                                                            ? 'fill-amber-400 text-amber-400'
                                                            : 'text-gray-300'
                                                    }`}
                                                />
                                            ))}
                                            <span className="ml-2 text-sm font-semibold">
                                                {report.review.rating}/5
                                            </span>
                                        </div>

                                        <p className="mt-3 text-sm leading-6 whitespace-pre-wrap">
                                            {report.review.comment ||
                                                'Aucun commentaire.'}
                                        </p>
                                    </section>

                                    <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                                        <button
                                            type="button"
                                            disabled={
                                                processingId === report.id
                                            }
                                            onClick={() =>
                                                dismissReport(report.id)
                                            }
                                            className="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <XCircle className="size-4" />
                                            Rejeter le signalement
                                        </button>

                                        <button
                                            type="button"
                                            disabled={
                                                processingId === report.id
                                            }
                                            onClick={() =>
                                                hideReportedReview(report.id)
                                            }
                                            className="inline-flex items-center justify-center gap-2 rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-white hover:bg-destructive/90 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <EyeOff className="size-4" />
                                            {processingId === report.id
                                                ? 'Traitement...'
                                                : 'Masquer l’avis'}
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
                            Aucun signalement en attente
                        </h2>

                        <p className="mt-2 text-sm text-muted-foreground">
                            Les nouveaux signalements d’avis apparaîtront ici.
                        </p>
                    </section>
                )}

                {reports.last_page > 1 && (
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {reports.current_page} sur {reports.last_page}
                        </p>

                        <nav
                            aria-label="Pagination des signalements"
                            className="flex flex-wrap justify-center gap-2"
                        >
                            {reports.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
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
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="inline-block rounded-lg border px-3 py-2 text-sm text-muted-foreground opacity-50"
                                    >
                                        {link.label
                                            .replace('&laquo;', '«')
                                            .replace('&raquo;', '»')}
                                    </span>
                                ),
                            )}
                        </nav>
                    </div>
                )}
            </main>
        </>
    );
}
