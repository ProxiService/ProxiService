import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

type SharedNotificationProps = Record<string, unknown> & {
    unreadNotificationsCount?: number;
    notificationsIndexUrl?: string | null;
};

export default function NotificationBell() {
    const { props } = usePage<SharedNotificationProps>();

    const unreadCount = props.unreadNotificationsCount ?? 0;
    const notificationsUrl = props.notificationsIndexUrl;

    if (typeof notificationsUrl !== 'string' || !notificationsUrl) {
        return null;
    }

    return (
        <Link
            href={notificationsUrl}
            aria-label={
                unreadCount > 0
                    ? `Notifications, ${unreadCount} non lue${unreadCount > 1 ? 's' : ''}`
                    : 'Notifications'
            }
            className="relative inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800"
        >
            <Bell className="size-5 text-indigo-600" />

            <span>Notifications</span>

            {unreadCount > 0 && (
                <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-bold text-white">
                    {unreadCount > 99 ? '99+' : unreadCount}
                </span>
            )}
        </Link>
    );
}
