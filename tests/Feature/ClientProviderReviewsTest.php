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

function createProviderReviewDisplayFixture(array $ratings = []): array
{
    $visitor = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $profile = new ProviderProfile([
        'business_name' => 'Artisan Étoile',
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

    $profile->services()->attach($service->id, [
        'price' => null,
        'is_active' => true,
    ]);

    foreach ($ratings as $index => $rating) {
        $reviewer = User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $serviceRequest = new ServiceRequestModel([
            'description' => 'Réparation du robinet de la cuisine.',
            'city' => 'Anyama',
            'neighborhood' => 'Centre',
            'is_urgent' => false,
        ]);

        $serviceRequest->client()->associate($reviewer);
        $serviceRequest->providerProfile()->associate($profile);
        $serviceRequest->service()->associate($service);
        $serviceRequest->status = 'completed';
        $serviceRequest->save();

        $review = new ServiceReview([
            'rating' => $rating,
            'comment' => 'Avis numéro '.($index + 1).'.',
        ]);

        $review->client()->associate($reviewer);
        $review->providerProfile()->associate($profile);
        $review->created_at = now()->subDays($index + 1);
        $serviceRequest->review()->save($review);
    }

    return compact('visitor', 'provider', 'profile', 'service');
}

test('le profil public affiche la moyenne et les avis du prestataire', function () {
    $fixture = createProviderReviewDisplayFixture([5, 4]);

    $this->actingAs($fixture['visitor'])
        ->get(route('client.services.providers.show', [
            'service' => $fixture['service']->slug,
            'providerProfile' => $fixture['profile']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/providers/show')
            ->where('reviewSummary.average', 4.5)
            ->where('reviewSummary.count', 2)
            ->has('reviews', 2)
            ->where('reviews.0.service_name', 'Réparation de robinet')
        );
});

test('la moyenne utilise tous les avis et la liste est limitée aux cinq plus récents', function () {
    $fixture = createProviderReviewDisplayFixture([5, 4, 3, 2, 1, 5]);

    $this->actingAs($fixture['visitor'])
        ->get(route('client.services.providers.show', [
            'service' => $fixture['service']->slug,
            'providerProfile' => $fixture['profile']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/providers/show')
            ->where('reviewSummary.average', 3.3)
            ->where('reviewSummary.count', 6)
            ->has('reviews', 5)
            ->where('reviews.0.comment', 'Avis numéro 1.')
        );
});

test('un profil sans avis ne reçoit pas de note moyenne fictive', function () {
    $fixture = createProviderReviewDisplayFixture();

    $this->actingAs($fixture['visitor'])
        ->get(route('client.services.providers.show', [
            'service' => $fixture['service']->slug,
            'providerProfile' => $fixture['profile']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/providers/show')
            ->where('reviewSummary.average', null)
            ->where('reviewSummary.count', 0)
            ->has('reviews', 0)
        );
});
