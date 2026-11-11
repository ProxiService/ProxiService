import { Head, Link, router, useForm } from '@inertiajs/react';
import { show as providerConversation } from '@/routes/prestataire/requests/messages';
import { store as storeProviderQuote } from '@/routes/prestataire/requests/quotes';
import ServiceRequestIssueForm from '@/components/service-request-issue-form';
import {
    finish as finishServiceRequest,
    start as startServiceRequest,
} from '@/routes/prestataire/requests';
import type { FormEvent } from 'react';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Clock3,
    MapPin,
    UserRound,
    Wrench,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

type ServiceRequestItem = {
    id: number;
    description: string;
    city: string;
    neighborhood: string;
    address: string | null;
    scheduled_at: string | null;
    is_urgent: boolean;
    urgent_dispatch_status: string | null;
    urgent_expires_at: string | null;
    status: string;
    dispute_status: string | null;
    dispute_reason: string | null;
    dispute_resolution: string | null;
    created_at: string | null;
    client: {
        name: string;
    };
    service: {
        name: string;
        slug: string;
        pricing_type: string;
    };
    quotes: ServiceQuoteItem[];
};

type ServiceQuoteItem = {
    id: number;
    amount: string | number;
    description: string;
    status: 'pending' | 'accepted' | 'rejected' | 'superseded';
    created_at: string | null;
    responded_at: string | null;
    commission: {
        gross: number;
        rate: number;
        commission: number;
        net: number;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedRequests = {
    data: ServiceRequestItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type PageProps = {
    requests: PaginatedRequests;
    commissionRate: number;
    view: 'requests' | 'messages';
};

const statusLabels: Record<string, string> = {
    pending: 'En attente',
    accepted: 'Acceptée',
    in_progress: 'En cours',
    awaiting_confirmation: 'À confirmer par le client',
    completed: 'Terminée',
    rejected: 'Refusée',
    expired: 'Expirée sans attribution',
};

const statusClasses: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    accepted: 'bg-emerald-100 text-emerald-800',
    in_progress: 'bg-blue-100 text-blue-800',
    awaiting_confirmation: 'bg-purple-100 text-purple-800',
    completed: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
};

function formatStatus(status: string): string {
    return statusLabels[status] ?? status;
}

function getStatusClass(status: string): string {
    return statusClasses[status] ?? 'bg-gray-100 text-gray-700';
}

function formatPaginationLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

function ProviderQuoteForm({
    requestId,
    commissionRate,
    urgent = false,
}: {
    requestId: number;
    commissionRate: number;
    urgent?: boolean;
}) {
    const form = useForm({
        amount: '',
        description: '',
    });

    function submitQuote(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.transform((data) => ({
            amount: data.amount,
            description: data.description.trim(),
        }));

        form.post(
            urgent
                ? `/prestataire/requests/${requestId}/accept-urgent`
                : storeProviderQuote.url(requestId),
            {
                preserveScroll: true,
                onSuccess: () => form.reset(),
            },
        );
    }

    return (
        <form onSubmit={submitQuote} className="mt-4 space-y-4">
            <div>
                <h4 className="font-semibold text-gray-900">
                    {urgent
                        ? 'Accepter et envoyer un devis'
                        : 'Proposer un devis'}
                </h4>

                <p className="mt-1 text-sm text-gray-600">
                    Indique le prix total proposé en FCFA et précise les travaux
                    inclus.
                </p>
            </div>

            {Number(form.data.amount) > 0 && (
                <p className="rounded-lg bg-indigo-50 p-3 text-sm text-indigo-900">
                    Commission plateforme ({commissionRate} %) :{' '}
                    {Math.round(
                        (Number(form.data.amount) * commissionRate) / 100,
                    ).toLocaleString('fr-FR')}{' '}
                    FCFA. Net estimé pour toi :{' '}
                    {(
                        Number(form.data.amount) -
                        Math.round(
                            (Number(form.data.amount) * commissionRate) / 100,
                        )
                    ).toLocaleString('fr-FR')}{' '}
                    FCFA.
                </p>
            )}

            <div>
                <label
                    htmlFor={`quote-amount-${requestId}`}
                    className="mb-1 block text-sm font-medium text-gray-700"
                >
                    Montant (FCFA)
                </label>

                <input
                    id={`quote-amount-${requestId}`}
                    type="number"
                    min="1"
                    max="999999999"
                    step="1"
                    required
                    value={form.data.amount}
                    onChange={(event) =>
                        form.setData('amount', event.target.value)
                    }
                    disabled={form.processing}
                    placeholder="Ex. 25000"
                    className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-base text-gray-900 outline-none placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 disabled:bg-gray-100"
                />

                {form.errors.amount && (
                    <p role="alert" className="mt-1 text-sm text-red-600">
                        {form.errors.amount}
                    </p>
                )}
            </div>

            <div>
                <label
                    htmlFor={`quote-description-${requestId}`}
                    className="mb-1 block text-sm font-medium text-gray-700"
                >
                    Description des travaux
                </label>

                <textarea
                    id={`quote-description-${requestId}`}
                    rows={4}
                    minLength={10}
                    maxLength={3000}
                    required
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    disabled={form.processing}
                    placeholder="Décris les travaux prévus et ce que le prix comprend."
                    className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-base text-gray-900 outline-none placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 disabled:bg-gray-100"
                />

                {form.errors.description && (
                    <p role="alert" className="mt-1 text-sm text-red-600">
                        {form.errors.description}
                    </p>
                )}
            </div>

            <button
                type="submit"
                disabled={form.processing}
                className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {form.processing ? 'Envoi en cours...' : 'Envoyer le devis'}
            </button>
        </form>
    );
}

export default function ProviderRequestsIndex({
    requests,
    commissionRate,
    view,
}: PageProps) {
    const [processingRequestId, setProcessingRequestId] = useState<
        number | null
    >(null);

    function handleDecision(requestId: number, action: 'accept' | 'reject') {
        const actionLabel = action === 'accept' ? 'accepter' : 'refuser';

        const confirmed = window.confirm(
            `Voulez-vous vraiment ${actionLabel} cette demande ?`,
        );

        if (!confirmed) {
            return;
        }

        setProcessingRequestId(requestId);

        router.patch(
            `/prestataire/requests/${requestId}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessingRequestId(null);
                },
            },
        );
    }

    function handleLifecycleAction(
        requestId: number,
        action: 'start' | 'finish',
    ) {
        const message =
            action === 'start'
                ? 'Confirmer le démarrage de cette intervention ?'
                : 'Signaler que cette intervention est terminée ?';

        if (!window.confirm(message)) {
            return;
        }

        setProcessingRequestId(requestId);

        const lifecycleAction =
            action === 'start' ? startServiceRequest : finishServiceRequest;

        router.patch(
            lifecycleAction.url(requestId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingRequestId(null),
            },
        );
    }

    return (
        <>
            <Head
                title={view === 'messages' ? 'Mes messages' : 'Demandes reçues'}
            />

            <div className="min-h-screen bg-gray-50 px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-5xl">
                    {/* En-tête */}
                    <div className="mb-8">
                        <Link
                            href="/prestataire/dashboard"
                            className="mb-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            ← Retour au tableau de bord
                        </Link>

                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                                    {view === 'messages'
                                        ? 'Mes conversations'
                                        : 'Demandes reçues'}
                                </h1>

                                <p className="mt-2 text-sm text-gray-600 sm:text-base">
                                    {view === 'messages'
                                        ? 'Retrouve les échanges avec les clients liés à tes prestations actives.'
                                        : 'Consulte les demandes de tes clients et décide si tu peux effectuer les prestations proposées.'}
                                </p>
                            </div>

                            <div className="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                                <Wrench className="size-5 text-indigo-600" />

                                <div>
                                    <p className="text-xs text-gray-500">
                                        {view === 'messages'
                                            ? 'Total des conversations'
                                            : 'Total des demandes'}
                                    </p>

                                    <p className="text-xl font-bold text-gray-900">
                                        {requests.total}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Liste des demandes */}
                    {requests.data.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
                            <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-indigo-50">
                                <CalendarClock className="size-7 text-indigo-600" />
                            </div>

                            <h2 className="text-lg font-semibold text-gray-900">
                                {view === 'messages'
                                    ? 'Aucune conversation disponible'
                                    : 'Aucune demande pour le moment'}
                            </h2>

                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600">
                                {view === 'messages'
                                    ? 'Les conversations s’afficheront ici après acceptation d’une demande.'
                                    : "Lorsqu'un client demandera l'une de tes prestations, sa demande apparaîtra ici."}
                            </p>

                            <Link
                                href="/prestataire/dashboard"
                                className="mt-6 inline-flex rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                            >
                                Retour au tableau de bord
                            </Link>
                        </div>
                    ) : (
                        <div className="space-y-5">
                            {requests.data.map((serviceRequest) => {
                                const isProcessing =
                                    processingRequestId === serviceRequest.id;

                                const isPending =
                                    serviceRequest.status === 'pending';

                                return (
                                    <article
                                        key={serviceRequest.id}
                                        className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                                    >
                                        {/* En-tête de la carte */}
                                        <div className="flex flex-col gap-4 border-b border-gray-100 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h2 className="text-lg font-bold text-gray-900">
                                                        {
                                                            serviceRequest
                                                                .service.name
                                                        }
                                                    </h2>

                                                    {serviceRequest.is_urgent && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
                                                            <AlertTriangle className="size-3.5" />
                                                            Urgente
                                                        </span>
                                                    )}
                                                </div>

                                                <p className="mt-1 text-sm text-gray-500">
                                                    Demande n°
                                                    {serviceRequest.id}
                                                    {serviceRequest.created_at && (
                                                        <>
                                                            {' '}
                                                            · Reçue le{' '}
                                                            {
                                                                serviceRequest.created_at
                                                            }
                                                        </>
                                                    )}
                                                </p>
                                            </div>

                                            <span
                                                className={`inline-flex w-fit items-center rounded-full px-3 py-1.5 text-sm font-semibold ${getStatusClass(serviceRequest.status)}`}
                                            >
                                                {formatStatus(
                                                    serviceRequest.status,
                                                )}
                                            </span>
                                        </div>

                                        {/* Corps de la carte */}
                                        <div className="space-y-5 px-5 py-5 sm:px-6">
                                            <div>
                                                <h3 className="mb-2 text-sm font-semibold text-gray-800">
                                                    Description du besoin
                                                </h3>

                                                <p className="text-sm leading-6 whitespace-pre-wrap text-gray-600">
                                                    {serviceRequest.description}
                                                </p>
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="flex items-start gap-3">
                                                    <UserRound className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                                    <div>
                                                        <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">
                                                            Client
                                                        </p>

                                                        <p className="mt-1 text-sm font-medium text-gray-900">
                                                            {
                                                                serviceRequest
                                                                    .client.name
                                                            }
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex items-start gap-3">
                                                    <MapPin className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                                    <div>
                                                        <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">
                                                            Lieu d'intervention
                                                        </p>

                                                        <p className="mt-1 text-sm font-medium text-gray-900">
                                                            {
                                                                serviceRequest.neighborhood
                                                            }
                                                            ,{' '}
                                                            {
                                                                serviceRequest.city
                                                            }
                                                        </p>

                                                        {serviceRequest.address && (
                                                            <p className="mt-1 text-sm text-gray-500">
                                                                {
                                                                    serviceRequest.address
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="flex items-start gap-3">
                                                    <CalendarClock className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                                    <div>
                                                        <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">
                                                            Date souhaitée
                                                        </p>

                                                        <p className="mt-1 text-sm font-medium text-gray-900">
                                                            {serviceRequest.scheduled_at ??
                                                                'À convenir avec le client'}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex items-start gap-3">
                                                    <Clock3 className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                                    <div>
                                                        <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">
                                                            État
                                                        </p>

                                                        <p className="mt-1 text-sm font-medium text-gray-900">
                                                            {serviceRequest.status ===
                                                            'pending'
                                                                ? 'Une réponse est attendue de ta part.'
                                                                : serviceRequest.status ===
                                                                    'accepted'
                                                                  ? 'La demande est acceptée. Tu peux démarrer l’intervention.'
                                                                  : serviceRequest.status ===
                                                                      'in_progress'
                                                                    ? 'L’intervention est en cours.'
                                                                    : serviceRequest.status ===
                                                                        'awaiting_confirmation'
                                                                      ? 'Le client doit confirmer la fin de la prestation.'
                                                                      : serviceRequest.status ===
                                                                          'completed'
                                                                        ? 'La prestation est terminée.'
                                                                        : 'Cette demande a été refusée.'}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {(serviceRequest.service
                                            .pricing_type === 'quote' ||
                                            serviceRequest.quotes.length >
                                                0) && (
                                            <section className="border-t border-gray-100 px-5 py-5 sm:px-6">
                                                <h3 className="text-base font-semibold text-gray-900">
                                                    Devis et prix proposés
                                                </h3>

                                                {serviceRequest.quotes.length >
                                                    0 && (
                                                    <div className="mt-4 space-y-3">
                                                        {serviceRequest.quotes.map(
                                                            (quote) => (
                                                                <div
                                                                    key={
                                                                        quote.id
                                                                    }
                                                                    className="rounded-xl border border-gray-200 bg-gray-50 p-4"
                                                                >
                                                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                                        <p className="text-lg font-bold text-gray-900">
                                                                            {Number(
                                                                                quote.amount,
                                                                            ).toLocaleString(
                                                                                'fr-FR',
                                                                            )}{' '}
                                                                            FCFA
                                                                        </p>

                                                                        <p className="mt-2 text-sm text-gray-600">
                                                                            Commission
                                                                            (
                                                                            {
                                                                                quote
                                                                                    .commission
                                                                                    .rate
                                                                            }{' '}
                                                                            %) :{' '}
                                                                            {quote.commission.commission.toLocaleString(
                                                                                'fr-FR',
                                                                            )}{' '}
                                                                            FCFA
                                                                            ·
                                                                            Net
                                                                            estimé
                                                                            :{' '}
                                                                            {quote.commission.net.toLocaleString(
                                                                                'fr-FR',
                                                                            )}{' '}
                                                                            FCFA
                                                                        </p>

                                                                        <span className="inline-flex w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-700">
                                                                            {{
                                                                                pending:
                                                                                    'En attente de réponse',
                                                                                accepted:
                                                                                    'Accepté',
                                                                                rejected:
                                                                                    'Refusé',
                                                                                superseded:
                                                                                    'Remplacé',
                                                                            }[
                                                                                quote
                                                                                    .status
                                                                            ] ??
                                                                                quote.status}
                                                                        </span>
                                                                    </div>

                                                                    <p className="mt-3 text-sm leading-6 whitespace-pre-wrap text-gray-600">
                                                                        {
                                                                            quote.description
                                                                        }
                                                                    </p>

                                                                    {quote.created_at && (
                                                                        <p className="mt-2 text-xs text-gray-400">
                                                                            Proposé
                                                                            le{' '}
                                                                            {
                                                                                quote.created_at
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}

                                                {serviceRequest.status ===
                                                    'accepted' &&
                                                    serviceRequest.service
                                                        .pricing_type ===
                                                        'quote' &&
                                                    !serviceRequest.quotes.some(
                                                        (quote) =>
                                                            quote.status ===
                                                                'pending' ||
                                                            quote.status ===
                                                                'accepted',
                                                    ) && (
                                                        <ProviderQuoteForm
                                                            requestId={
                                                                serviceRequest.id
                                                            }
                                                            commissionRate={
                                                                commissionRate
                                                            }
                                                        />
                                                    )}

                                                {serviceRequest.status ===
                                                    'accepted' &&
                                                    serviceRequest.quotes.some(
                                                        (quote) =>
                                                            quote.status ===
                                                            'pending',
                                                    ) && (
                                                        <p className="mt-4 text-sm text-amber-700">
                                                            Un devis a été
                                                            envoyé. Attends la
                                                            réponse du client
                                                            avant d'en proposer
                                                            un autre.
                                                        </p>
                                                    )}

                                                {serviceRequest.quotes.some(
                                                    (quote) =>
                                                        quote.status ===
                                                        'accepted',
                                                ) && (
                                                    <p className="mt-4 text-sm font-medium text-emerald-700">
                                                        Le client a accepté un
                                                        devis. Le montant
                                                        convenu est enregistré.
                                                    </p>
                                                )}
                                            </section>
                                        )}

                                        {/* Actions */}
                                        {isPending &&
                                            serviceRequest.is_urgent && (
                                                <div className="border-t border-red-100 bg-red-50 px-5 py-4 sm:px-6">
                                                    <p className="mb-3 text-sm text-red-900">
                                                        Demande diffusée aux
                                                        prestataires proches. La
                                                        position précise du
                                                        client n’est pas
                                                        partagée.
                                                    </p>
                                                    <ProviderQuoteForm
                                                        requestId={
                                                            serviceRequest.id
                                                        }
                                                        commissionRate={
                                                            commissionRate
                                                        }
                                                        urgent
                                                    />
                                                </div>
                                            )}

                                        {isPending &&
                                            !serviceRequest.is_urgent && (
                                                <div className="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                                                    <button
                                                        type="button"
                                                        disabled={isProcessing}
                                                        onClick={() =>
                                                            handleDecision(
                                                                serviceRequest.id,
                                                                'reject',
                                                            )
                                                        }
                                                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        <XCircle className="size-4" />

                                                        {isProcessing
                                                            ? 'Traitement...'
                                                            : 'Refuser'}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        disabled={isProcessing}
                                                        onClick={() =>
                                                            handleDecision(
                                                                serviceRequest.id,
                                                                'accept',
                                                            )
                                                        }
                                                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        <CheckCircle2 className="size-4" />

                                                        {isProcessing
                                                            ? 'Traitement...'
                                                            : 'Accepter'}
                                                    </button>
                                                </div>
                                            )}

                                        {[
                                            'accepted',
                                            'in_progress',
                                            'awaiting_confirmation',
                                            'completed',
                                        ].includes(serviceRequest.status) && (
                                            <div className="border-t border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
                                                <button
                                                    type="button"
                                                    disabled={
                                                        processingRequestId !==
                                                        null
                                                    }
                                                    onClick={() =>
                                                        handleLifecycleAction(
                                                            serviceRequest.id,
                                                            'start',
                                                        )
                                                    }
                                                    className="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {processingRequestId ===
                                                    serviceRequest.id
                                                        ? 'Traitement...'
                                                        : "Commencer l'intervention"}
                                                </button>
                                            </div>
                                        )}

                                        {serviceRequest.status ===
                                            'in_progress' && (
                                            <div className="border-t border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
                                                <button
                                                    type="button"
                                                    disabled={
                                                        processingRequestId !==
                                                        null
                                                    }
                                                    onClick={() =>
                                                        handleLifecycleAction(
                                                            serviceRequest.id,
                                                            'finish',
                                                        )
                                                    }
                                                    className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {processingRequestId ===
                                                    serviceRequest.id
                                                        ? 'Traitement...'
                                                        : "Signaler la fin de l'intervention"}
                                                </button>
                                            </div>
                                        )}

                                        {serviceRequest.status ===
                                            'awaiting_confirmation' && (
                                            <div className="border-t border-gray-100 bg-purple-50 px-5 py-4 text-sm text-purple-900 sm:px-6">
                                                L'intervention est terminée
                                                selon ta déclaration. La
                                                prestation attend maintenant la
                                                confirmation du client.
                                            </div>
                                        )}

                                        {[
                                            'accepted',
                                            'in_progress',
                                            'awaiting_confirmation',
                                            'completed',
                                        ].includes(serviceRequest.status) && (
                                            <div className="border-t border-gray-100 bg-gray-50 px-5 py-4 sm:px-6">
                                                <Link
                                                    href={providerConversation.url(
                                                        serviceRequest.id,
                                                    )}
                                                    className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                                                >
                                                    Ouvrir la conversation
                                                </Link>
                                            </div>
                                        )}
                                        <div className="px-5 pb-4 sm:px-6">
                                            <ServiceRequestIssueForm
                                                endpoint={`/prestataire/requests/${serviceRequest.id}`}
                                                status={serviceRequest.status}
                                                disputeStatus={
                                                    serviceRequest.dispute_status
                                                }
                                                disputeReason={
                                                    serviceRequest.dispute_reason
                                                }
                                                disputeResolution={
                                                    serviceRequest.dispute_resolution
                                                }
                                            />
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    {/* Pagination */}
                    {requests.last_page > 1 && (
                        <div className="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-gray-600">
                                Page {requests.current_page} sur{' '}
                                {requests.last_page}
                            </p>

                            <nav
                                aria-label="Pagination des demandes"
                                className="flex flex-wrap items-center gap-1"
                            >
                                {requests.links.map((link, index) => {
                                    const label = formatPaginationLabel(
                                        link.label,
                                    );

                                    const className = [
                                        'rounded-lg px-3 py-2 text-sm font-medium',
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-white text-gray-700 hover:bg-gray-100',
                                        !link.url
                                            ? 'cursor-not-allowed opacity-40'
                                            : '',
                                    ].join(' ');

                                    if (!link.url) {
                                        return (
                                            <span
                                                key={`${label}-${index}`}
                                                className={className}
                                            >
                                                {label}
                                            </span>
                                        );
                                    }

                                    return (
                                        <Link
                                            key={`${label}-${index}`}
                                            href={link.url}
                                            preserveScroll
                                            aria-current={
                                                link.active ? 'page' : undefined
                                            }
                                            className={className}
                                        >
                                            {label}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
