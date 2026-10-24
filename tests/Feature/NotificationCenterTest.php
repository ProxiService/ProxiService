<?php

use App\Models\User;
use App\Notifications\NewServiceRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createNotificationForCenterTest(User $user): string
{
    $user->notify(
        new NewServiceRequestNotification(
            serviceRequestId: 123,
            serviceName: 'Réparation de robinet',
            clientName: 'Client de test',
        ),
    );

    return (string) $user->notifications()->latest()->firstOrFail()->id;
}

test('un prestataire peut consulter ses notifications', function () {
    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $notificationId = createNotificationForCenterTest($provider);

    $otherProvider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    createNotificationForCenterTest($otherProvider);

    $this->actingAs($provider)
        ->get(route('prestataire.notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('notifications.data', 1)
            ->where('unreadCount', 1)
            ->where('unreadNotificationsCount', 1)
            ->where(
                'notificationsIndexUrl',
                route('prestataire.notifications.index'),
            )
            ->where('notifications.data.0.id', $notificationId)
            ->where(
                'notifications.data.0.title',
                'Nouvelle demande reçue',
            )
            ->where('notifications.data.0.is_read', false)
        );
});

test('un utilisateur peut marquer sa notification comme lue', function () {
    $client = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $notificationId = createNotificationForCenterTest($client);

    $this->actingAs($client)
        ->from(route('client.notifications.index'))
        ->patch(route('client.notifications.read', [
            'notification' => $notificationId,
        ]))
        ->assertRedirect(route('client.notifications.index'));

    expect(
        $client->notifications()->findOrFail($notificationId)->read_at,
    )->not->toBeNull();

    expect($client->unreadNotifications()->count())->toBe(0);
});

test('un utilisateur ne peut pas marquer la notification d’un autre comme lue', function () {
    $owner = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $notificationId = createNotificationForCenterTest($owner);

    $this->actingAs($otherClient)
        ->patch(route('client.notifications.read', [
            'notification' => $notificationId,
        ]))
        ->assertNotFound();

    expect($owner->unreadNotifications()->count())->toBe(1);
});

test('un prestataire peut marquer toutes ses notifications comme lues', function () {
    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    createNotificationForCenterTest($provider);
    createNotificationForCenterTest($provider);

    expect($provider->unreadNotifications()->count())->toBe(2);

    $this->actingAs($provider)
        ->from(route('prestataire.notifications.index'))
        ->patch(route('prestataire.notifications.read-all'))
        ->assertRedirect(route('prestataire.notifications.index'));

    expect($provider->unreadNotifications()->count())->toBe(0);
});
