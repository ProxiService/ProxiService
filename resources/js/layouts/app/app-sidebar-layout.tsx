import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    Home,
    MessageCircle,
    Search,
    UserRound,
    Wallet,
} from 'lucide-react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const page = usePage();
    const auth = page.props.auth as { user: { role?: string } | null };
    const url = page.url;
    const isUrgentFlow = url === '/client/requests/urgent';
    const role = auth.user?.role;
    const items =
        role === 'prestataire'
            ? [
                  {
                      label: 'Accueil',
                      href: '/prestataire/dashboard',
                      icon: Home,
                  },
                  {
                      label: 'Demandes',
                      href: '/prestataire/requests',
                      icon: ClipboardList,
                  },
                  {
                      label: 'Messages',
                      href: '/prestataire/requests?view=messages',
                      icon: MessageCircle,
                  },
                  {
                      label: 'Profil',
                      href: '/settings/profile',
                      icon: UserRound,
                  },
              ]
            : role === 'admin'
              ? [
                    { label: 'Accueil', href: '/admin/dashboard', icon: Home },
                    {
                        label: 'Demandes',
                        href: '/admin/requests/disputes',
                        icon: ClipboardList,
                    },
                    {
                        label: 'Commissions',
                        href: '/admin/commissions',
                        icon: Wallet,
                    },
                    {
                        label: 'Profil',
                        href: '/settings/profile',
                        icon: UserRound,
                    },
                ]
              : [
                    { label: 'Accueil', href: '/client/dashboard', icon: Home },
                    {
                        label: 'Recherche',
                        href: '/client/search',
                        icon: Search,
                    },
                    {
                        label: 'Demandes',
                        href: '/client/requests',
                        icon: ClipboardList,
                    },
                    {
                        label: 'Messages',
                        href: '/client/requests?view=messages',
                        icon: MessageCircle,
                    },
                    {
                        label: 'Profil',
                        href: '/settings/profile',
                        icon: UserRound,
                    },
                ];
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="min-w-0 overflow-x-clip pb-[calc(5rem+env(safe-area-inset-bottom))] md:pb-0"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            {!isUrgentFlow && (
                <nav
                    aria-label="Navigation mobile"
                    className="fixed inset-x-0 bottom-0 z-50 grid border-t bg-background/95 px-1 pt-1 pb-[env(safe-area-inset-bottom)] shadow-[0_-6px_20px_rgb(15_23_42/0.08)] backdrop-blur md:hidden"
                    style={{
                        gridTemplateColumns: `repeat(${items.length}, minmax(0, 1fr))`,
                    }}
                >
                    {items.map(({ label, href, icon: Icon }) => {
                        const samePath =
                            url.split('?')[0] === href.split('?')[0];
                        const messageView = url.includes('view=messages');
                        const targetMessageView =
                            href.includes('view=messages');
                        const active = samePath
                            ? messageView === targetMessageView
                            : href !== '/client/search' &&
                              href !== '/client/dashboard' &&
                              url.startsWith(`${href}/`);
                        return (
                            <Link
                                key={label}
                                href={href}
                                aria-current={active ? 'page' : undefined}
                                className={`flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-lg text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring ${active ? 'text-teal-800 dark:text-teal-300' : 'text-muted-foreground'}`}
                            >
                                <Icon aria-hidden="true" className="size-5" />
                                <span>{label}</span>
                            </Link>
                        );
                    })}
                </nav>
            )}
        </AppShell>
    );
}
