<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServicePayment;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createQuoteFixture(?string $status = 'pending'): array
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
        'business_name' => 'Artisan devis',
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
        'name' => 'Installation de pompe',
        'slug' => 'installation-pompe',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $serviceRequest = new ServiceRequest([
        'description' => 'Je souhaite faire installer une pompe à eau.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_urgent' => false,
    ]);

    $serviceRequest->client()->associate($client);
    $serviceRequest->providerProfile()->associate($profile);
    $serviceRequest->service()->associate($service);
    $serviceRequest->status = $status;
    $serviceRequest->save();

    return compact('client', 'provider', 'profile', 'service', 'serviceRequest');
}

test('le prestataire peut proposer un devis pour une demande de type quote', function () {
    $fixture = createQuoteFixture('accepted');

    $this->actingAs($fixture['provider'])
        ->post(route('prestataire.requests.quotes.store', $fixture['serviceRequest']), [
            'amount' => 25000,
            'description' => 'Installation complète de la pompe avec vérification du réseau.',
        ])
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_quotes', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'provider_profile_id' => $fixture['profile']->id,
        'status' => 'pending',
        'amount' => '25000.00',
    ]);
});

test('le prestataire ne peut pas proposer un devis avant d’accepter la demande', function () {
    $fixture = createQuoteFixture('pending');

    $this->actingAs($fixture['provider'])
        ->post(route('prestataire.requests.quotes.store', $fixture['serviceRequest']), [
            'amount' => 25000,
            'description' => 'Installation complète de la pompe avec vérification du réseau.',
        ])
        ->assertStatus(409);

    $this->assertDatabaseCount('service_quotes', 0);
});

test('le client peut accepter un devis et le demande passe en accepted', function () {
    $fixture = createQuoteFixture('pending');

    $quote = $fixture['serviceRequest']->quotes()->create([
        'provider_profile_id' => $fixture['profile']->id,
        'amount' => 25000,
        'description' => 'Installation complète de la pompe',
        'status' => 'pending',
    ]);

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.quotes.accept', [
            'serviceRequest' => $fixture['serviceRequest'],
            'serviceQuote' => $quote,
        ]))
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_quotes', [
        'id' => $quote->id,
        'status' => 'accepted',
    ]);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);
});

test('un seul devis peut être accepté et le montant net est visible avant acceptation', function () {
    $fixture = createQuoteFixture('accepted');
    $pendingQuote = $fixture['serviceRequest']->quotes()->create([
        'provider_profile_id' => $fixture['profile']->id,
        'amount' => 25000,
        'description' => 'Installation complète de la pompe et contrôle final.',
        'status' => 'pending',
    ]);

    $this->actingAs($fixture['provider'])
        ->get(route('prestataire.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('commissionRate', 10)
            ->where('requests.data.0.quotes.0.commission.commission', 2500)
            ->where('requests.data.0.quotes.0.commission.net', 22500)
        );

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.quotes.accept', [
            'serviceRequest' => $fixture['serviceRequest'],
            'serviceQuote' => $pendingQuote,
        ]))
        ->assertRedirect(route('client.requests.index'));

    $secondQuote = $fixture['serviceRequest']->quotes()->create([
        'provider_profile_id' => $fixture['profile']->id,
        'amount' => 27000,
        'description' => 'Installation alternative avec contrôle de pression.',
        'status' => 'pending',
    ]);

    $this->actingAs($fixture['client'])
        ->patch(route('client.requests.quotes.accept', [
            'serviceRequest' => $fixture['serviceRequest'],
            'serviceQuote' => $secondQuote,
        ]))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_quotes', ['id' => $pendingQuote->id, 'status' => 'accepted']);
    $this->assertDatabaseHas('service_quotes', ['id' => $secondQuote->id, 'status' => 'pending']);
});

test('le prestataire ne peut pas démarrer une demande quote sans devis accepté', function () {
    $fixture = createQuoteFixture('accepted');

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

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', $fixture['serviceRequest']))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);
});

test('un devis accepté ne suffit pas sans paiement confirmé', function () {
    $fixture = createQuoteFixture('accepted');

    $fixture['serviceRequest']->quotes()->create([
        'provider_profile_id' => $fixture['profile']->id,
        'amount' => 25000,
        'description' => 'Installation complète de la pompe',
        'status' => 'accepted',
        'responded_at' => now(),
    ]);

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', $fixture['serviceRequest']))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);
});

test('le prestataire peut démarrer après acceptation du devis et confirmation du paiement', function () {
    $fixture = createQuoteFixture('accepted');

    $quote = $fixture['serviceRequest']->quotes()->create([
        'provider_profile_id' => $fixture['profile']->id,
        'amount' => 25000,
        'description' => 'Installation complète de la pompe',
        'status' => 'accepted',
        'responded_at' => now(),
    ]);

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

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.start', $fixture['serviceRequest']))
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'in_progress',
    ]);

    $this->assertDatabaseHas('service_quotes', [
        'id' => $quote->id,
        'status' => 'accepted',
    ]);
});
