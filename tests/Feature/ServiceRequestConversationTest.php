<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServicePayment;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Préparer les participants et une demande pour tester la messagerie.
 */
function createConversationMessagingFixture(
    string $status = 'accepted',
): array {
    $client = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $profile = new ProviderProfile([
        'business_name' => 'Artisan Messagerie',
        'bio' => 'Profil créé pour les tests.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_available' => true,
    ]);

    $profile->user()->associate($provider);
    $profile->save();
    $profile->forceFill(['status' => 'approved'])->save();

    $category = Category::create([
        'name' => 'Plomberie',
        'slug' => 'plomberie',
        'description' => 'Travaux de plomberie.',
        'is_active' => true,
    ]);

    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'description' => 'Réparation de robinets.',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $serviceRequest = new ServiceRequestModel([
        'description' => 'Mon robinet fuit depuis hier.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'address' => 'Près du marché',
        'is_urgent' => false,
    ]);

    $serviceRequest->client()->associate($client);
    $serviceRequest->providerProfile()->associate($profile);
    $serviceRequest->service()->associate($service);
    $serviceRequest->status = $status;
    $serviceRequest->save();

    return compact(
        'client',
        'provider',
        'profile',
        'category',
        'service',
        'serviceRequest',
    );
}

test('le client peut consulter la conversation après acceptation', function () {
    $fixture = createConversationMessagingFixture();

    $this->actingAs($fixture['client'])
        ->get(route(
            'client.requests.messages.show',
            $fixture['serviceRequest'],
        ))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conversations/show')
            ->where('conversation.id', $fixture['serviceRequest']->id)
            ->where(
                'conversation.service_name',
                'Réparation de robinet',
            )
            ->where(
                'conversation.provider_name',
                'Artisan Messagerie',
            )
            ->where('currentUserId', $fixture['client']->id)
            ->where('conversation.address', null)
            ->has('messages', 0)
        );
});

test('le prestataire associé peut consulter la même conversation', function () {
    $fixture = createConversationMessagingFixture();

    $this->actingAs($fixture['provider'])
        ->get(route(
            'prestataire.requests.messages.show',
            $fixture['serviceRequest'],
        ))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conversations/show')
            ->where('conversation.id', $fixture['serviceRequest']->id)
            ->where(
                'conversation.client_name',
                $fixture['client']->name,
            )
            ->where('currentUserId', $fixture['provider']->id)
        );
});

test('les participants peuvent continuer à échanger pendant et après la prestation', function (
    string $status,
) {
    $fixture = createConversationMessagingFixture($status);

    $this->actingAs($fixture['client'])
        ->get(route(
            'client.requests.messages.show',
            $fixture['serviceRequest'],
        ))
        ->assertOk();

    $this->actingAs($fixture['provider'])
        ->get(route(
            'prestataire.requests.messages.show',
            $fixture['serviceRequest'],
        ))
        ->assertOk();

    $this->actingAs($fixture['client'])
        ->post(
            route(
                'client.requests.messages.store',
                $fixture['serviceRequest'],
            ),
            ['body' => 'Je confirme notre échange pendant la prestation.'],
        )
        ->assertRedirect(route(
            'client.requests.messages.show',
            $fixture['serviceRequest'],
        ));

    $this->assertDatabaseHas('service_request_messages', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'sender_id' => $fixture['client']->id,
        'body' => 'Je confirme notre échange pendant la prestation.',
    ]);
})->with([
    'intervention en cours' => 'in_progress',
    'en attente de confirmation' => 'awaiting_confirmation',
    'prestation terminée' => 'completed',
]);

test('un participant peut envoyer un message et son identité est définie par le serveur', function () {
    $fixture = createConversationMessagingFixture();

    $body = 'Bonjour, je suis disponible demain pour la réparation.';

    $this->actingAs($fixture['client'])
        ->post(
            route(
                'client.requests.messages.store',
                $fixture['serviceRequest'],
            ),
            [
                'body' => $body,

                // Ces données malveillantes doivent être ignorées.
                'sender_id' => $fixture['provider']->id,
                'service_request_id' => 999999,
            ],
        )
        ->assertRedirect(route(
            'client.requests.messages.show',
            $fixture['serviceRequest'],
        ));

    $this->assertDatabaseHas('service_request_messages', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'sender_id' => $fixture['client']->id,
        'body' => $body,
    ]);

    $this->assertDatabaseMissing('service_request_messages', [
        'service_request_id' => 999999,
        'body' => $body,
    ]);
});

test('un autre client ne peut ni lire ni envoyer des messages dans cette conversation', function () {
    $fixture = createConversationMessagingFixture('in_progress');

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($otherClient)
        ->get(route(
            'client.requests.messages.show',
            $fixture['serviceRequest'],
        ))
        ->assertNotFound();

    $this->actingAs($otherClient)
        ->post(
            route(
                'client.requests.messages.store',
                $fixture['serviceRequest'],
            ),
            ['body' => 'Je tente un accès non autorisé.'],
        )
        ->assertNotFound();

    $this->assertDatabaseCount('service_request_messages', 0);
});

test('la conversation est inaccessible tant que la demande est en attente', function () {
    $fixture = createConversationMessagingFixture('pending');

    $this->actingAs($fixture['client'])
        ->get(route(
            'client.requests.messages.show',
            $fixture['serviceRequest'],
        ))
        ->assertNotFound();

    $this->actingAs($fixture['provider'])
        ->get(route(
            'prestataire.requests.messages.show',
            $fixture['serviceRequest'],
        ))
        ->assertNotFound();

    $this->actingAs($fixture['client'])
        ->post(
            route(
                'client.requests.messages.store',
                $fixture['serviceRequest'],
            ),
            ['body' => 'Bonjour, je souhaite discuter de la prestation.'],
        )
        ->assertNotFound();

    $this->assertDatabaseCount('service_request_messages', 0);
});

test('les coordonnées sont bloquées avant paiement et autorisées après confirmation', function () {
    $fixture = createConversationMessagingFixture();
    $url = route('client.requests.messages.store', $fixture['serviceRequest']);

    foreach ([
        'Appelle-moi au +225 07 12 34 56 78',
        'Écris à artisan@example.ci',
        'Consulte https://exemple.ci/devis',
        'Mon numéro est 0 7 1 2 3 4 5 6 7 8',
    ] as $body) {
        $this->actingAs($fixture['client'])
            ->post($url, ['body' => $body])
            ->assertSessionHasErrors('body');
    }

    $this->assertDatabaseCount('service_request_messages', 0);
    $this->actingAs($fixture['client'])
        ->post($url, ['body' => 'Je passerai le 2026-11-11 vers 10 h.'])
        ->assertRedirect(route('client.requests.messages.show', $fixture['serviceRequest']));

    $this->assertDatabaseHas('service_request_messages', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'body' => 'Je passerai le 2026-11-11 vers 10 h.',
    ]);

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'provider_reference' => 'paid-chat-'.$fixture['serviceRequest']->id,
        'amount' => 20000,
        'currency' => 'XOF',
        'status' => 'completed',
        'environment' => 'sandbox',
    ]);
    $payment->client()->associate($fixture['client']);
    $fixture['serviceRequest']->payments()->save($payment);

    $this->actingAs($fixture['client'])
        ->post($url, ['body' => 'Appelle-moi au +225 07 12 34 56 78'])
        ->assertRedirect(route('client.requests.messages.show', $fixture['serviceRequest']));

    $this->actingAs($fixture['client'])
        ->get(route('client.requests.messages.show', $fixture['serviceRequest']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.address', 'Près du marché'));

    $this->assertDatabaseHas('service_request_messages', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'body' => 'Appelle-moi au +225 07 12 34 56 78',
    ]);
});

test('la messagerie est limitée pour empêcher le spam', function () {
    $fixture = createConversationMessagingFixture();
    $url = route('client.requests.messages.store', $fixture['serviceRequest']);

    foreach (range(1, 12) as $index) {
        $this->actingAs($fixture['client'])
            ->post($url, ['body' => 'Message de test numéro '.$index])
            ->assertRedirect();
    }

    $this->actingAs($fixture['client'])
        ->post($url, ['body' => 'Message au-delà de la limite'])
        ->assertStatus(429);
});
