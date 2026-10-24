<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceCommission;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCommissionAccountingFixture(
    string $pricingType = 'quote',
    bool $withAcceptedQuote = true,
    ?float $basePrice = null,
    ?float $providerPrice = null,
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
        'business_name' => 'Prestataire Commission Test',
        'bio' => 'Profil créé pour les tests financiers.',
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
        'pricing_type' => $pricingType,
        'base_price' => $basePrice,
        'is_active' => true,
    ]);

    $profile->services()->attach($service->id, [
        'price' => $providerPrice,
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
    $serviceRequest->status = 'awaiting_confirmation';
    $serviceRequest->save();

    if ($pricingType === 'quote' && $withAcceptedQuote) {
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

    return compact(
        'client',
        'provider',
        'profile',
        'service',
        'serviceRequest',
    );
}

test('la confirmation enregistre la commission sur le devis accepté', function () {
    config(['proxiservices.commission_rate' => 10.0]);

    $fixture = makeCommissionAccountingFixture();

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.confirm-completion', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_commissions', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'gross_amount' => 25000,
        'commission_rate' => 10,
        'commission_amount' => 2500,
        'provider_amount' => 22500,
        'collection_status' => 'pending',
    ]);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'completed',
    ]);

    expect($fixture['serviceRequest']->commission)
        ->toBeInstanceOf(ServiceCommission::class);
});

test('la commission d’un prix fixe utilise le prix du prestataire', function () {
    config(['proxiservices.commission_rate' => 10.0]);

    $fixture = makeCommissionAccountingFixture(
        pricingType: 'fixed',
        withAcceptedQuote: false,
        basePrice: 15000,
        providerPrice: 18000,
    );

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.confirm-completion', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_commissions', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'gross_amount' => 18000,
        'commission_rate' => 10,
        'commission_amount' => 1800,
        'provider_amount' => 16200,
    ]);
});

test('le prix de base est utilisé si le service fixe ne possède pas de tarif prestataire', function () {
    config(['proxiservices.commission_rate' => 10.0]);

    $fixture = makeCommissionAccountingFixture(
        pricingType: 'fixed',
        withAcceptedQuote: false,
        basePrice: 12000,
        providerPrice: null,
    );

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.confirm-completion', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_commissions', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'gross_amount' => 12000,
        'commission_amount' => 1200,
        'provider_amount' => 10800,
    ]);
});

test('la prestation ne peut pas être finalisée sans devis accepté', function () {
    $fixture = makeCommissionAccountingFixture(
        pricingType: 'quote',
        withAcceptedQuote: false,
    );

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.confirm-completion', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertStatus(409);

    $this->assertDatabaseCount('service_commissions', 0);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'awaiting_confirmation',
    ]);
});

test('un autre client ne peut pas confirmer la prestation ni créer sa commission', function () {
    $fixture = makeCommissionAccountingFixture();

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($otherClient)
        ->patch(route('client.requests.confirm-completion', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertNotFound();

    $this->assertDatabaseCount('service_commissions', 0);
});

test('une commission ne peut pas être enregistrée deux fois', function () {
    $fixture = makeCommissionAccountingFixture();

    $url = route('client.requests.confirm-completion', [
        'serviceRequest' => $fixture['serviceRequest']->id,
    ]);

    $this->actingAs($fixture['client'])
        ->patch($url)
        ->assertRedirect(route('client.requests.index'));

    $this->actingAs($fixture['client'])
        ->patch($url)
        ->assertStatus(409);

    $this->assertDatabaseCount('service_commissions', 1);
});

test('un taux de commission supérieur à 100 est rejeté', function () {
    config(['proxiservices.commission_rate' => 110.0]);

    $fixture = makeCommissionAccountingFixture();

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.confirm-completion', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertStatus(500);

    $this->assertDatabaseCount('service_commissions', 0);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'awaiting_confirmation',
    ]);
});
