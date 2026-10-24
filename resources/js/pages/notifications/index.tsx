import { Head, Link, router } from '@inertiajs/react';
import {
    Bell,
    Check,
    CheckCheck,
    ExternalLink,
    Inbox,
} from 'lucide-react';
import { useState } from 'react';
import { dashboard as dashboardRoute } from '@/routes';

type NotificationItem = {
    id: string;
    title: string;
    message: string;
    url: string | null;
    is_read: boolean;
    created_at: string | null;
    read_url: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedNotifications = {
    data: NotificationItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

type PageProps = {
    notifications: PaginatedNotifications;
    unreadCount: number;
    markAllReadUrl: string;
};

function Pagination({
    notifications,
}: {
    notifications: PaginatedNotifications;
}) {
    return (
        <div className="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-gray-600 dark:text-gray-400">
                Page {notifications.current_page} sur {notifications.last_page}
            </p>

            <nav
                aria-label="Pagination des notifications"
                className="flex flex-wrap items-center gap-1"
            >
                {notifications.links.map((link, index) => {
                    const label = link.label
                        .replace('&laquo;', '«')
                        .replace('&raquo;', '»');

                    const className = [
                        'rounded-lg px-3 py-2 text-sm font-medium',
                        link.active
                            ? 'bg-indigo-600 text-white'
                            : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800',
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
                            aria-current={link.active ? 'page' : undefined}
                            className={className}
                        >
                            {label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}

export default function NotificationsIndex({
    notifications,
    unreadCount,
    markAllReadUrl,
}: PageProps) {
    const [processingKey, setProcessingKey] = useState<string | null>(null);

    function markAsRead(notification: NotificationItem) {
        if (notification.is_read || processingKey !== null) {
            return;
        }

        setProcessingKey(notification.id);

        router.patch(
            notification.read_url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingKey(null),
            },
        );
    }

    function markAllAsRead() {
        if (unreadCount === 0 || processingKey !== null) {
            return;
        }

        setProcessingKey('all');

        router.patch(
            markAllReadUrl,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessingKey(null),
            },
        );
    }

    return (
        <>
            <Head title="Mes notifications" />

            <main className="min-h-screen bg-gray-50 px-4 py-8 dark:bg-gray-950 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-8">
                        <div className="mb-4">
                            <Link
                                href={dashboardRoute()}
                                className="text-sm font-medium text-indigo-600 transition hover:text-indigo-800"
                            >
                                ← Retour au tableau de bord
                            </Link>
                        </div>

                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-950">
                                    <Bell className="size-6 text-indigo-600" />
                                </div>

                                <div>
                                    <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100 sm:text-3xl">
                                        Mes notifications
                                    </h1>

                                    <p className="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                        Retrouve les informations importantes
                                        concernant tes demandes et tes
                                        prestations.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={markAllAsRead}
                                disabled={
                                    unreadCount === 0 || processingKey !== null
                                }
                                className="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800"
                            >
                                <CheckCheck className="size-4" />

                                {processingKey === 'all'
                                    ? 'Traitement...'
                                    : 'Tout marquer comme lu'}
                            </button>
                        </div>

                        <div className="mt-6 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Notifications au total
                                </p>

                                <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {notifications.total}
                                </p>
                            </div>

                            <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Notifications non lues
                                </p>

                                <p className="mt-1 text-2xl font-bold text-indigo-600">
                                    {unreadCount}
                                </p>
                            </div>
                        </div>
                    </div>

                    {notifications.data.length === 0 ? (
                        <section className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-700 dark:bg-gray-900">
                            <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                <Inbox className="size-7 text-gray-500" />
                            </div>

                            <h2 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Aucune notification
                            </h2>

                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600 dark:text-gray-400">
                                Tes notifications apparaîtront ici lorsque
                                tu recevras des informations importantes
                                sur ProxiServices.
                            </p>
                        </section>
                    ) : (
                        <div className="space-y-3">
                            {notifications.data.map((notification) => {
                                const isProcessing =
                                    processingKey === notification.id;

                                return (
                                    <article
                                        key={notification.id}
                                        className={`rounded-2xl border bg-white p-5 shadow-sm transition sm:p-6 dark:bg-gray-900 ${
                                            notification.is_read
                                                ? 'border-gray-200 dark:border-gray-800'
                                                : 'border-indigo-200 ring-1 ring-indigo-50 dark:border-indigo-900 dark:ring-indigo-950'
                                        }`}
                                    >
                                        <div className="flex items-start gap-3">
                                            <div
                                                className={`mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl ${
                                                    notification.is_read
                                                        ? 'bg-gray-100 dark:bg-gray-800'
                                                        : 'bg-indigo-50 dark:bg-indigo-950'
                                                }`}
                                            >
                                                {notification.is_read ? (
                                                    <Check className="size-5 text-gray-500" />
                                                ) : (
                                                    <Bell className="size-5 text-indigo-600" />
                                                )}
                                            </div>

                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">
                                                        {notification.title}
                                                    </h2>

                                                    {!notification.is_read && (
                                                        <span className="inline-flex w-fit rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                                                            Non lue
                                                        </span>
                                                    )}
                                                </div>

                                                <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-gray-600 dark:text-gray-400">
                                                    {notification.message}
                                                </p>

                                                {notification.created_at && (
                                                    <p className="mt-3 text-xs text-gray-400">
                                                        {notification.created_at}
                                                    </p>
                                                )}

                                                <div className="mt-4 flex flex-wrap items-center gap-4">
                                                    {notification.url && (
                                                        <Link
                                                            href={notification.url}
                                                            className="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                                                        >
                                                            Ouvrir
                                                            <ExternalLink className="size-3.5" />
                                                        </Link>
                                                    )}

                                                    {!notification.is_read && (
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                isProcessing ||
                                                                processingKey !==
                                                                    null
                                                            }
                                                            onClick={() =>
                                                                markAsRead(
                                                                    notification,
                                                                )
                                                            }
                                                            className="text-sm font-medium text-gray-600 underline decoration-gray-300 underline-offset-4 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-100"
                                                        >
                                                            {isProcessing
                                                                ? 'Traitement...'
                                                                : 'Marquer comme lue'}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}

                    {notifications.last_page > 1 && (
                        <Pagination notifications={notifications} />
                    )}
                </div>
            </main>
        </>
    );
}
