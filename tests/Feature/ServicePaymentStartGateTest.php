<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServicePayment;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPaymentStartGateFixture(
    string $pricingType = 'quote',
    string $paymentStatus = 'pending',
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
        'business_name' => 'Prestataire Vérification Paiement',
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
        'description' => 'Réparation de robinets.',
        'pricing_type' => $pricingType,
        'base_price' => $pricingType === 'fixed' ? 15000 : null,
        'is_active' => true,
    ]);

    $profile->services()->attach($service->id, [
        'price' => $pricingType === 'fixed' ? 15000 : null,
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
    $serviceRequest->status = 'accepted';
    $serviceRequest->save();

    if ($pricingType === 'quote') {
        $quote = new ServiceQuote([
            'provider_profile_id' => $profile->id,
            'amount' => 25000,
            'description' => 'Réparation complète et remplacement des pièces.',
        ]);

        $serviceRequest->quotes()->save($quote);

        $quote->status = 'accepted';
        $quote->responded_at = now();
        $quote->save();
    }

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'provider_reference' => 'MTX-GATE-'.$serviceRequest->id,
        'amount' => $pricingType === 'fixed' ? 15000 : 25000,
        'currency' => 'XOF',
        'status' => $paymentStatus,
        'environment' => 'sandbox',
        'paid_at' => $paymentStatus === 'completed' ? now() : null,
    ]);

    $payment->client()->associate($client);
    $serviceRequest->payments()->save($payment);

    return compact(
        'client',
        'provider',
        'profile',
        'service',
        'serviceRequest',
        'payment',
    );
}

test('le prestataire ne peut pas démarrer avec un paiement en attente', function () {
    $fixture = createPaymentStartGateFixture(
        pricingType: 'quote',
        paymentStatus: 'pending',
    );

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);
});

test('le prestataire peut démarrer après confirmation du paiement et acceptation du devis', function () {
    $fixture = createPaymentStartGateFixture(
        pricingType: 'quote',
        paymentStatus: 'completed',
    );

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'in_progress',
    ]);
});

test('un prix fixe nécessite également un paiement confirmé', function () {
    $fixture = createPaymentStartGateFixture(
        pricingType: 'fixed',
        paymentStatus: 'pending',
    );

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);
});

test('une prestation à prix fixe peut démarrer après le paiement confirmé', function () {
    $fixture = createPaymentStartGateFixture(
        pricingType: 'fixed',
        paymentStatus: 'completed',
    );

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'in_progress',
    ]);
});

test('un paiement échoué ne permet pas de démarrer la prestation', function () {
    $fixture = createPaymentStartGateFixture(
        pricingType: 'quote',
        paymentStatus: 'failed',
    );

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);
});
