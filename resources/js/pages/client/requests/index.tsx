import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { confirmCompletion as confirmServiceCompletion } from '@/routes/client/requests';
import { show as clientConversation } from '@/routes/client/requests/messages';
import { store as storeServiceReview } from '@/routes/client/requests/review';
import { providers as serviceProviders } from '@/routes/client/services';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Clock3,
    MapPin,
    Star,
    UserRound,
    Wrench,
    XCircle,
} from 'lucide-react';

type ServiceRequestItem = {
    id: number;
    description: string;
    city: string;
    neighborhood: string;
    address: string | null;
    scheduled_at: string | null;
    is_urgent: boolean;
    status: string;
    created_at: string | null;
    review: {
        rating: number;
        comment: string | null;
    } | null;
    service: {
        name: string;
        slug: string;
    };
    provider: {
        name: string;
        city: string | null;
        neighborhood: string | null;
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
};

const statusLabels: Record<string, string> = {
    pending: 'En attente',
    accepted: 'Acceptée',
    in_progress: 'En cours',
    awaiting_confirmation: 'À confirmer',
    completed: 'Terminée',
    rejected: 'Refusée',
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

function ReviewForm({ requestId }: { requestId: number }) {
    const form = useForm({
        rating: 5,
        comment: '',
    });

    function submitReview(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(storeServiceReview.url(requestId), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={submitReview} className="space-y-4">
            <div>
                <h3 className="text-base font-semibold text-gray-900">
                    Évalue cette prestation
                </h3>

                <p className="mt-1 text-sm text-gray-600">
                    Ton avis aidera les autres clients à choisir un prestataire.
                </p>
            </div>

            <fieldset>
                <legend className="mb-2 text-sm font-medium text-gray-800">
                    Ta note
                </legend>

                <div className="flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map((value) => (
                        <button
                            key={value}
                            type="button"
                            aria-label={`Donner ${value} étoile${value > 1 ? 's' : ''}`}
                            aria-pressed={form.data.rating === value}
                            onClick={() => form.setData('rating', value)}
                            className="rounded-md p-1 transition hover:bg-amber-50 focus:ring-2 focus:ring-amber-400 focus:outline-none"
                        >
                            <Star
                                className={`size-7 ${
                                    value <= form.data.rating
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'text-gray-300'
                                }`}
                            />
                        </button>
                    ))}
                </div>

                {form.errors.rating && (
                    <p role="alert" className="mt-1 text-sm text-red-600">
                        {form.errors.rating}
                    </p>
                )}
            </fieldset>

            <div>
                <label
                    htmlFor={`review-comment-${requestId}`}
                    className="mb-2 block text-sm font-medium text-gray-800"
                >
                    Commentaire (facultatif)
                </label>

                <textarea
                    id={`review-comment-${requestId}`}
                    value={form.data.comment}
                    onChange={(event) =>
                        form.setData('comment', event.target.value)
                    }
                    rows={3}
                    maxLength={2000}
                    placeholder="Comment s'est déroulée la prestation ?"
                    disabled={form.processing}
                    className="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 disabled:bg-gray-100"
                />

                {form.errors.comment && (
                    <p role="alert" className="mt-1 text-sm text-red-600">
                        {form.errors.comment}
                    </p>
                )}

                <p className="mt-1 text-xs text-gray-500">
                    {form.data.comment.length}/2000 caractères
                </p>
            </div>

            <button
                type="submit"
                disabled={form.processing}
                className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {form.processing ? 'Envoi en cours...' : 'Publier mon avis'}
            </button>
        </form>
    );
}

export default function MyServiceRequestsIndex({ requests }: PageProps) {
    const [processingRequestId, setProcessingRequestId] = useState<
        number | null
    >(null);

    function confirmCompletion(requestId: number) {
        if (
            !window.confirm(
                'Confirmes-tu que la prestation a bien été terminée ?',
            )
        ) {
            return;
        }

        setProcessingRequestId(requestId);

        router.patch(
            confirmServiceCompletion.url(requestId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingRequestId(null),
            },
        );
    }

    return (
        <>
            <Head title="Mes demandes envoyées" />

            <div className="min-h-screen bg-gray-50 px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-5xl">
                    {/* En-tête */}
                    <div className="mb-8">
                        <Link
                            href="/client/dashboard"
                            className="mb-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            ← Retour au tableau de bord
                        </Link>

                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                                    Mes demandes envoyées
                                </h1>

                                <p className="mt-2 text-sm leading-6 text-gray-600 sm:text-base">
                                    Retrouve tes demandes de prestations et
                                    vérifie leur état.
                                </p>
                            </div>

                            <div className="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                                <Wrench className="size-5 text-indigo-600" />

                                <div>
                                    <p className="text-xs text-gray-500">
                                        Total de mes demandes
                                    </p>

                                    <p className="text-xl font-bold text-gray-900">
                                        {requests.total}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Aucune demande */}
                    {requests.data.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center">
                            <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-indigo-50">
                                <CalendarClock className="size-7 text-indigo-600" />
                            </div>

                            <h2 className="text-lg font-semibold text-gray-900">
                                Tu n'as pas encore envoyé de demande
                            </h2>

                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600">
                                Explore les prestations disponibles et contacte
                                un prestataire pour demander une intervention.
                            </p>

                            <Link
                                href="/client/dashboard"
                                className="mt-6 inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                            >
                                Rechercher une prestation
                            </Link>
                        </div>
                    ) : (
                        <div className="space-y-5">
                            {requests.data.map((serviceRequest) => (
                                <article
                                    key={serviceRequest.id}
                                    className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                                >
                                    {/* Titre et statut */}
                                    <div className="flex flex-col gap-4 border-b border-gray-100 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="text-lg font-bold text-gray-900">
                                                    {
                                                        serviceRequest.service
                                                            .name
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
                                                Demande n°{serviceRequest.id}
                                                {serviceRequest.created_at && (
                                                    <>
                                                        {' '}
                                                        · Envoyée le{' '}
                                                        {
                                                            serviceRequest.created_at
                                                        }
                                                    </>
                                                )}
                                            </p>
                                        </div>

                                        <span
                                            className={`inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold ${getStatusClass(serviceRequest.status)}`}
                                        >
                                            {serviceRequest.status ===
                                                'pending' && (
                                                <Clock3 className="size-4" />
                                            )}

                                            {serviceRequest.status ===
                                                'accepted' && (
                                                <CheckCircle2 className="size-4" />
                                            )}

                                            {serviceRequest.status ===
                                                'rejected' && (
                                                <XCircle className="size-4" />
                                            )}

                                            {formatStatus(
                                                serviceRequest.status,
                                            )}
                                        </span>
                                    </div>

                                    {/* Informations de la demande */}
                                    <div className="space-y-5 px-5 py-5 sm:px-6">
                                        <div>
                                            <h3 className="mb-2 text-sm font-semibold text-gray-800">
                                                Description de ton besoin
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
                                                        Prestataire
                                                    </p>

                                                    <p className="mt-1 text-sm font-medium text-gray-900">
                                                        {
                                                            serviceRequest
                                                                .provider.name
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
                                                        , {serviceRequest.city}
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
                                                            'À convenir avec le prestataire'}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex items-start gap-3">
                                                <Clock3 className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                                <div>
                                                    <p className="text-xs font-medium tracking-wide text-gray-500 uppercase">
                                                        État de la demande
                                                    </p>

                                                    <p className="mt-1 text-sm leading-5 text-gray-600">
                                                        {serviceRequest.status ===
                                                        'pending'
                                                            ? 'Le prestataire doit encore répondre.'
                                                            : serviceRequest.status ===
                                                                'accepted'
                                                              ? 'Le prestataire a accepté ta demande. L’intervention peut être organisée.'
                                                              : serviceRequest.status ===
                                                                  'in_progress'
                                                                ? 'Le prestataire a commencé l’intervention.'
                                                                : serviceRequest.status ===
                                                                    'awaiting_confirmation'
                                                                  ? 'Le prestataire signale que le travail est terminé. Vérifie la prestation avant de la confirmer.'
                                                                  : serviceRequest.status ===
                                                                      'completed'
                                                                    ? 'Tu as confirmé que la prestation est terminée.'
                                                                    : 'Le prestataire a refusé cette demande. Tu peux en rechercher un autre.'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                            <Link
                                                href={serviceProviders.url(
                                                    serviceRequest.service.slug,
                                                )}
                                                className="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                                            >
                                                Voir les prestataires pour ce
                                                service
                                                <span aria-hidden="true">
                                                    →
                                                </span>
                                            </Link>

                                            {[
                                                'accepted',
                                                'in_progress',
                                                'awaiting_confirmation',
                                                'completed',
                                            ].includes(
                                                serviceRequest.status,
                                            ) && (
                                                <Link
                                                    href={clientConversation.url(
                                                        serviceRequest.id,
                                                    )}
                                                    className="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700"
                                                >
                                                    Ouvrir la conversation
                                                </Link>
                                            )}

                                            {serviceRequest.status ===
                                                'awaiting_confirmation' && (
                                                <button
                                                    type="button"
                                                    disabled={
                                                        processingRequestId !==
                                                        null
                                                    }
                                                    onClick={() =>
                                                        confirmCompletion(
                                                            serviceRequest.id,
                                                        )
                                                    }
                                                    className="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    {processingRequestId ===
                                                    serviceRequest.id
                                                        ? 'Confirmation...'
                                                        : 'Confirmer la prestation'}
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {serviceRequest.status === 'completed' && (
                                        <div className="border-t border-gray-100 bg-white px-5 py-5 sm:px-6">
                                            {serviceRequest.review ? (
                                                <div>
                                                    <h3 className="text-base font-semibold text-gray-900">
                                                        Ton avis
                                                    </h3>

                                                    <div
                                                        className="mt-2 flex items-center gap-1"
                                                        aria-label={`Note : ${serviceRequest.review.rating} sur 5`}
                                                    >
                                                        {[1, 2, 3, 4, 5].map(
                                                            (value) => (
                                                                <Star
                                                                    key={value}
                                                                    className={`size-5 ${
                                                                        value <=
                                                                        (serviceRequest
                                                                            .review
                                                                            ?.rating ??
                                                                            0)
                                                                            ? 'fill-amber-400 text-amber-400'
                                                                            : 'text-gray-300'
                                                                    }`}
                                                                />
                                                            ),
                                                        )}

                                                        <span className="ml-2 text-sm font-semibold text-gray-700">
                                                            {
                                                                serviceRequest
                                                                    .review
                                                                    .rating
                                                            }
                                                            /5
                                                        </span>
                                                    </div>

                                                    {serviceRequest.review
                                                        .comment && (
                                                        <p className="mt-3 text-sm leading-6 whitespace-pre-wrap text-gray-600">
                                                            {
                                                                serviceRequest
                                                                    .review
                                                                    .comment
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            ) : (
                                                <ReviewForm
                                                    requestId={
                                                        serviceRequest.id
                                                    }
                                                />
                                            )}
                                        </div>
                                    )}
                                </article>
                            ))}
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
                                aria-label="Pagination de mes demandes"
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
