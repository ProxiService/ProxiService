<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\ServiceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createClientRequestTrackingFixture(): array
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
        'business_name' => 'Artisan Exemple',
        'bio' => 'Prestataire créé pour les tests.',
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

    $firstRequest = createTrackedServiceRequest(
        $client,
        $profile,
        $service,
    );

    return compact(
        'client',
        'provider',
        'profile',
        'category',
        'service',
        'firstRequest',
    );
}

function createTrackedServiceRequest(
    User $client,
    ProviderProfile $profile,
    Service $service,
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
    $serviceRequest->status = 'pending';
    $serviceRequest->save();

    return $serviceRequest;
}

test('un client consulte uniquement ses propres demandes', function () {
    $fixture = createClientRequestTrackingFixture();

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $otherRequest = createTrackedServiceRequest(
        $otherClient,
        $fixture['profile'],
        $fixture['service'],
    );

    $this->actingAs($fixture['client'])
        ->get(route('client.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/requests/index')
            ->has('requests.data', 1)
            ->where(
                'requests.data.0.id',
                $fixture['firstRequest']->id,
            )
            ->where(
                'requests.data.0.provider.name',
                'Artisan Exemple',
            )
            ->where('requests.data.0.status', 'pending')
            ->where('requests.data.0.review', null)
        );

    expect($otherRequest->client_id)
        ->not->toBe($fixture['client']->id);
});

test('le client reçoit les détails de son avis dans la liste de ses demandes', function () {
    $fixture = createClientRequestTrackingFixture();

    $fixture['firstRequest']->status = 'completed';
    $fixture['firstRequest']->save();

    $review = new ServiceReview([
        'rating' => 4,
        'comment' => 'Travail sérieux et soigné.',
    ]);
    $review->serviceRequest()->associate($fixture['firstRequest']);
    $review->client()->associate($fixture['client']);
    $review->providerProfile()->associate($fixture['profile']);
    $review->save();

    $this->actingAs($fixture['client'])
        ->get(route('client.requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/requests/index')
            ->where('requests.data.0.review.rating', 4)
            ->where(
                'requests.data.0.review.comment',
                'Travail sérieux et soigné.',
            )
        );
});

test('un visiteur non connecté ne peut pas consulter ses demandes', function () {
    $this->get(route('client.requests.index'))
        ->assertRedirect(route('login'));
});
