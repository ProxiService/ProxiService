<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Créer un profil de prestataire approuvé.
 */
function createProxiProviderProfile(User $user): ProviderProfile
{
    $profile = new ProviderProfile([
        'business_name' => 'Prestataire de test',
        'bio' => 'Prestataire créé pour les tests.',
        'phone' => '0700000000',
        'years_experience' => 3,
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_available' => true,
    ]);

    $profile->user()->associate($user);
    $profile->save();

    // Le statut ne doit pas être modifiable via le remplissage ordinaire.
    $profile->forceFill(['status' => 'approved'])->save();

    return $profile;
}

/**
 * Préparer un client, un prestataire, un profil, une prestation et une demande.
 */
function createProxiProviderRequestFixture(string $status = 'pending'): array
{
    $client = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $profile = createProxiProviderProfile($provider);

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
        'description' => 'Réparation et remplacement de robinets.',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $serviceRequest = createProxiServiceRequest(
        $client,
        $profile,
        $service,
        $status,
    );

    return compact(
        'client',
        'provider',
        'profile',
        'category',
        'service',
        'serviceRequest',
    );
}

/**
 * Créer une demande liée à un client, un profil et une prestation.
 */
function createProxiServiceRequest(
    User $client,
    ProviderProfile $profile,
    Service $service,
    string $status = 'pending',
): ServiceRequestModel {
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

    return $serviceRequest;
}

test('un prestataire ne voit que ses propres demandes', function () {
    $fixture = createProxiProviderRequestFixture();

    $otherProvider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $otherProfile = createProxiProviderProfile($otherProvider);

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $otherRequest = createProxiServiceRequest(
        $otherClient,
        $otherProfile,
        $fixture['service'],
    );

    $this->actingAs($fixture['provider'])
        ->get(route('prestataire.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('prestataire/requests/index')
            ->has('requests.data', 1)
            ->where(
                'requests.data.0.id',
                $fixture['serviceRequest']->id,
            )
            ->where('requests.data.0.status', 'pending')
        );

    expect($otherRequest->id)
        ->not->toBe($fixture['serviceRequest']->id);
});

test('un prestataire peut accepter sa propre demande en attente', function () {
    $fixture = createProxiProviderRequestFixture();

    $this->actingAs($fixture['provider'])
        ->patch(route(
            'prestataire.requests.accept',
            $fixture['serviceRequest'],
        ))
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);
});

test('un prestataire peut refuser sa propre demande en attente', function () {
    $fixture = createProxiProviderRequestFixture();

    $this->actingAs($fixture['provider'])
        ->patch(route(
            'prestataire.requests.reject',
            $fixture['serviceRequest'],
        ))
        ->assertRedirect(route('prestataire.requests.index'));

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'rejected',
    ]);
});

test('un prestataire ne peut pas traiter la demande d’un autre prestataire', function () {
    $fixture = createProxiProviderRequestFixture();

    $otherProvider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $otherProfile = createProxiProviderProfile($otherProvider);

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $otherRequest = createProxiServiceRequest(
        $otherClient,
        $otherProfile,
        $fixture['service'],
    );

    $this->actingAs($fixture['provider'])
        ->patch(route('prestataire.requests.accept', $otherRequest))
        ->assertNotFound();

    $this->assertDatabaseHas('service_requests', [
        'id' => $otherRequest->id,
        'status' => 'pending',
    ]);
});

test('une demande déjà traitée ne peut pas être acceptée à nouveau', function () {
    $fixture = createProxiProviderRequestFixture('rejected');

    $this->actingAs($fixture['provider'])
        ->patch(route(
            'prestataire.requests.accept',
            $fixture['serviceRequest'],
        ))
        ->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'rejected',
    ]);
});
