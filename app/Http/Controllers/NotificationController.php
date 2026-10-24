<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Afficher les notifications de l'utilisateur connecté.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $routePrefix = match ($user->role) {
            'client' => 'client.notifications',
            'prestataire' => 'prestataire.notifications',
            'admin' => 'admin.notifications',
            default => abort(403),
        };

        $notifications = $user->notifications()
            ->latest()
            ->paginate(15)
            ->through(fn ($notification): array => [
                'id' => (string) $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->format('d/m/Y H:i'),
                'read_url' => route(
                    "{$routePrefix}.read",
                    ['notification' => $notification->id],
                ),
            ]);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'markAllReadUrl' => route("{$routePrefix}.read-all"),
        ]);
    }

    /**
     * Marquer une notification appartenant à l'utilisateur comme lue.
     */
    public function markAsRead(
        Request $request,
        string $notification,
    ): RedirectResponse {
        $record = $request->user()
            ->notifications()
            ->findOrFail($notification);

        $record->markAsRead();

        return back()->with(
            'success',
            'La notification a été marquée comme lue.',
        );
    }

    /**
     * Marquer toutes les notifications non lues comme lues.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with(
            'success',
            'Toutes les notifications ont été marquées comme lues.',
        );
    }
}
