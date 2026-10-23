<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\ServiceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Préparer une demande et ses participants pour tester les avis.
 */
function createServiceReviewFeatureFixture(
    string $status = 'completed',
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
        'business_name' => 'Artisan Avis Test',
        'bio' => 'Profil de test.',
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

test('un client peut noter une prestation terminée', function () {
    $fixture = createServiceReviewFeatureFixture();

    $this->actingAs($fixture['client'])
        ->post(
            route(
                'client.requests.review.store',
                $fixture['serviceRequest'],
            ),
            [
                'rating' => 5,
                'comment' => 'Très bon travail, prestataire ponctuel.',
            ],
        )
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_reviews', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'client_id' => $fixture['client']->id,
        'provider_profile_id' => $fixture['profile']->id,
        'rating' => 5,
        'comment' => 'Très bon travail, prestataire ponctuel.',
    ]);

    expect(
        $fixture['serviceRequest']->fresh()->review,
    )->toBeInstanceOf(ServiceReview::class);
});

test('un client ne peut pas noter la prestation d’un autre client', function () {
    $fixture = createServiceReviewFeatureFixture();

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($otherClient)
        ->post(
            route(
                'client.requests.review.store',
                $fixture['serviceRequest'],
            ),
            [
                'rating' => 5,
                'comment' => 'Avis non autorisé.',
            ],
        )
        ->assertNotFound();

    $this->assertDatabaseCount('service_reviews', 0);
});

test('un client ne peut pas noter une prestation qui n’est pas terminée', function () {
    $fixture = createServiceReviewFeatureFixture('awaiting_confirmation');

    $this->actingAs($fixture['client'])
        ->post(
            route(
                'client.requests.review.store',
                $fixture['serviceRequest'],
            ),
            [
                'rating' => 4,
                'comment' => 'La prestation attend encore confirmation.',
            ],
        )
        ->assertStatus(409);

    $this->assertDatabaseCount('service_reviews', 0);
});

test('un client ne peut pas laisser deux avis pour la même prestation', function () {
    $fixture = createServiceReviewFeatureFixture();

    $url = route(
        'client.requests.review.store',
        $fixture['serviceRequest'],
    );

    $this->actingAs($fixture['client'])
        ->post($url, [
            'rating' => 5,
            'comment' => 'Premier avis.',
        ])
        ->assertRedirect(route('client.requests.index'));

    $this->actingAs($fixture['client'])
        ->post($url, [
            'rating' => 2,
            'comment' => 'Deuxième avis.',
        ])
        ->assertStatus(409);

    $this->assertDatabaseCount('service_reviews', 1);

    $this->assertDatabaseHas('service_reviews', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'rating' => 5,
        'comment' => 'Premier avis.',
    ]);
});

test('la note doit être comprise entre 1 et 5', function () {
    $fixture = createServiceReviewFeatureFixture();

    $this->actingAs($fixture['client'])
        ->post(
            route(
                'client.requests.review.store',
                $fixture['serviceRequest'],
            ),
            [
                'rating' => 6,
                'comment' => 'Cette note est invalide.',
            ],
        )
        ->assertSessionHasErrors('rating');

    $this->assertDatabaseCount('service_reviews', 0);
});

test('un commentaire est facultatif', function () {
    $fixture = createServiceReviewFeatureFixture();

    $this->actingAs($fixture['client'])
        ->post(
            route(
                'client.requests.review.store',
                $fixture['serviceRequest'],
            ),
            ['rating' => 4],
        )
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_reviews', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'client_id' => $fixture['client']->id,
        'rating' => 4,
        'comment' => null,
    ]);
});
