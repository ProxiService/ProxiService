<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServicePayment;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use App\Notifications\ServiceRequestStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLifecycleProxiFixture(string $status = 'accepted'): array
{
    $client = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $profile = new ProviderProfile([
        'business_name' => 'Artisan Test',
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
        'is_active' => true,
    ]);

    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'pricing_type' => 'fixed',
        'base_price' => 5000,
        'is_active' => true,
    ]);

    $serviceRequest = new ServiceRequestModel([
        'description' => 'Réparation du robinet de ma cuisine.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
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
        'service',
        'serviceRequest',
    );
}

test('le prestataire peut démarrer une demande acceptée', function () {
    $fixture = createLifecycleProxiFixture('accepted');

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'completed',
        'environment' => 'sandbox',
        'paid_at' => now(),
    ]);
    $payment->client()->associate($fixture['client']);
    $fixture['serviceRequest']->payments()->save($payment);

    $this->actingAs($fixture['provider'])
        ->patch(route(
            'prestataire.requests.start',
            $fixture['serviceRequest'],
        ))
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'in_progress',
    ]);
});

test('le prestataire ne peut pas démarrer une demande en attente', function () {
    $fixture = createLifecycleProxiFixture('pending');

    $this->actingAs($fixture['provider'])
        ->patch(route(
            'prestataire.requests.start',
            $fixture['serviceRequest'],
        ))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'pending',
    ]);
});

test('le prestataire peut signaler la fin d’une intervention en cours', function () {
    $fixture = createLifecycleProxiFixture('in_progress');

    $this->actingAs($fixture['provider'])
        ->patch(route(
            'prestataire.requests.finish',
            $fixture['serviceRequest'],
        ))
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'awaiting_confirmation',
    ]);
});

test('le client peut confirmer une prestation en attente de confirmation', function () {
    $fixture = createLifecycleProxiFixture('awaiting_confirmation');

    $fixture['service']->pricing_type = 'quote';
    $fixture['service']->save();

    $quote = new ServiceQuote([
        'provider_profile_id' => $fixture['profile']->id,
        'amount' => 25000,
        'description' => 'Réparation complète et remplacement des pièces.',
    ]);

    $fixture['serviceRequest']->quotes()->save($quote);

    $quote->status = 'accepted';
    $quote->responded_at = now();
    $quote->save();

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'amount' => 25000,
        'currency' => 'XOF',
        'status' => 'completed',
        'environment' => 'sandbox',
        'paid_at' => now(),
    ]);

    $payment->client()->associate($fixture['client']);
    $fixture['serviceRequest']->payments()->save($payment);

    $this->actingAs($fixture['client'])
        ->patch(route(
            'client.requests.confirm-completion',
            $fixture['serviceRequest'],
        ))
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'completed',
    ]);
});

test('un autre client ne peut pas confirmer cette prestation', function () {
    $fixture = createLifecycleProxiFixture('awaiting_confirmation');

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($otherClient)
        ->patch(route(
            'client.requests.confirm-completion',
            $fixture['serviceRequest'],
        ))
        ->assertNotFound();

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'awaiting_confirmation',
    ]);
});

test('une prestation en cours ne peut pas être confirmée comme terminée', function () {
    $fixture = createLifecycleProxiFixture('in_progress');

    $this->actingAs($fixture['client'])
        ->patch(route(
            'client.requests.confirm-completion',
            $fixture['serviceRequest'],
        ))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'in_progress',
    ]);
});

test('l’acceptation d’une demande notifie le client', function () {
    $fixture = createLifecycleProxiFixture('pending');

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.accept', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('prestataire.requests.index'));

    $notification = $fixture['client']->notifications()
        ->where('type', ServiceRequestStatusNotification::class)
        ->firstOrFail();

    expect($notification->data['status'])->toBe('accepted');
    expect($notification->data['service_request_id'])
        ->toBe($fixture['serviceRequest']->id);
});

test('le refus d’une demande notifie le client', function () {
    $fixture = createLifecycleProxiFixture('pending');

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.reject', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('prestataire.requests.index'));

    $notification = $fixture['client']->notifications()
        ->where('type', ServiceRequestStatusNotification::class)
        ->firstOrFail();

    expect($notification->data['status'])->toBe('rejected');
});

test('le démarrage d’une intervention notifie le client', function () {
    $fixture = createLifecycleProxiFixture('accepted');

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'completed',
        'environment' => 'sandbox',
        'paid_at' => now(),
    ]);

    $payment->client()->associate($fixture['client']);
    $fixture['serviceRequest']->payments()->save($payment);

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('prestataire.requests.index'));

    $notification = $fixture['client']->notifications()
        ->where('type', ServiceRequestStatusNotification::class)
        ->firstOrFail();

    expect($notification->data['status'])->toBe('in_progress');
});

test('la fin signalée par le prestataire notifie le client', function () {
    $fixture = createLifecycleProxiFixture('in_progress');

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.finish', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('prestataire.requests.index'));

    $notification = $fixture['client']->notifications()
        ->where('type', ServiceRequestStatusNotification::class)
        ->firstOrFail();

    expect($notification->data['status'])
        ->toBe('awaiting_confirmation');

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'awaiting_confirmation',
    ]);
});

test('la confirmation finale du client notifie le prestataire', function () {
    $fixture = createLifecycleProxiFixture('awaiting_confirmation');

    $fixture['service']->pricing_type = 'quote';
    $fixture['service']->save();

    $quote = new ServiceQuote([
        'provider_profile_id' => $fixture['profile']->id,
        'amount' => 25000,
        'description' => 'Réparation complète et remplacement des pièces.',
    ]);

    $fixture['serviceRequest']->quotes()->save($quote);

    $quote->status = 'accepted';
    $quote->responded_at = now();
    $quote->save();

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'provider_reference' => 'MTX-LIFECYCLE-NOTIF-'.$fixture['serviceRequest']->id,
        'amount' => 25000,
        'currency' => 'XOF',
        'status' => 'completed',
        'environment' => 'sandbox',
        'paid_at' => now(),
    ]);

    $payment->client()->associate($fixture['client']);
    $fixture['serviceRequest']->payments()->save($payment);

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.confirm-completion', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('client.requests.index'));

    $notification = $fixture['provider']->notifications()
        ->where('type', ServiceRequestStatusNotification::class)
        ->firstOrFail();

    expect($notification->data['status'])->toBe('completed');

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'completed',
    ]);
});
