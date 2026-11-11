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

test('clients only see approved providers actively offering the service in their area', function () {
    $client = User::factory()->create();

    $client->forceFill([
        'role' => 'client',
        'email_verified_at' => now(),
    ])->save();

    $category = Category::create([
        'name' => 'Plomberie',
        'slug' => 'plomberie',
        'description' => 'Services de plomberie.',
        'icon' => 'wrench',
        'is_active' => true,
    ]);

    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'description' => 'Réparation de robinet.',
        'pricing_type' => 'fixed',
        'base_price' => 5000,
        'estimated_duration_minutes' => 45,
        'is_active' => true,
    ]);

    $createProvider = function (
        string $businessName,
        string $status,
        string $city,
        bool $offerIsActive = true
    ) use ($service): ProviderProfile {
        $user = User::factory()->create();

        $user->forceFill([
            'role' => 'prestataire',
            'email_verified_at' => now(),
        ])->save();

        $profile = $user->providerProfile()->create([
            'business_name' => $businessName,
            'city' => $city,
            'neighborhood' => 'Centre',
            'is_available' => true,
            'latitude' => 5.5,
            'longitude' => -4.05,
        ]);

        $profile->forceFill([
            'status' => $status,
        ])->save();

        $profile->services()->attach($service->id, [
            'price' => 5500,
            'is_active' => $offerIsActive,
        ]);

        return $profile;
    };

    $listedProvider = $createProvider(
        'Jean Plomberie',
        'approved',
        'Anyama'
    );

    $createProvider(
        'Profil en attente',
        'pending',
        'Anyama'
    );

    $createProvider(
        'Profil rejeté',
        'rejected',
        'Anyama'
    );

    $createProvider(
        'Offre désactivée',
        'approved',
        'Anyama',
        false
    );

    $createProvider(
        'Prestataire à Abobo',
        'approved',
        'Abobo'
    );

    $this->actingAs($client)
        ->post('/client/services/reparation-robinet/providers', [
            'city' => 'Anyama',
            'latitude' => 5.5,
            'longitude' => -4.0,
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/services/providers')
            ->where('service.slug', 'reparation-robinet')
            ->where('filters.city', 'Anyama')
            ->has('providers', 1)
            ->where('providers.0.business_name', 'Jean Plomberie')
            ->where('providers.0.city', 'Anyama')
            ->where('providers.0.distance_km', 5.5)
        );

    $this->actingAs($client)
        ->get(route('client.services.providers.show', [
            'service' => $service,
            'providerProfile' => $listedProvider,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/providers/show')
            ->where('service.slug', 'reparation-robinet')
            ->where('provider.business_name', 'Jean Plomberie')
            ->where('provider.price', '5500.00')
        );
});

test('la recherche de prestataires est bloquée sans géolocalisation', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

    $category = Category::create([
        'name' => 'Plomberie', 'slug' => 'plomberie', 'is_active' => true,
    ]);
    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $this->actingAs($client)
        ->get(route('client.services.providers', $service->slug).'?latitude=5.5&longitude=-4')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/services/providers')
            ->where('locationRequired', true)
            ->has('providers', 0)
        );
});

test('la recherche refuse une coordonnée hors limites', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

    $category = Category::create([
        'name' => 'Plomberie', 'slug' => 'plomberie', 'is_active' => true,
    ]);
    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $this->actingAs($client)
        ->post(route('client.services.providers.search', $service->slug), ['latitude' => 91, 'longitude' => 0])
        ->assertSessionHasErrors('latitude');
});

test('la requête de recherche exige les deux coordonnées GPS', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
    $category = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'is_active' => true]);
    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $this->actingAs($client)
        ->post(route('client.services.providers.search', $service->slug), [])
        ->assertSessionHasErrors(['latitude', 'longitude']);
});

test('la recherche classe à distance croissante et masque les coordonnées exactes', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
    $category = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'is_active' => true]);
    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    foreach ([
        ['Proche', 5.51, -4.0],
        ['Éloigné', 5.3, -4.0],
    ] as [$name, $latitude, $longitude]) {
        $user = User::factory()->create(['role' => 'prestataire', 'email_verified_at' => now()]);
        $profile = $user->providerProfile()->create([
            'business_name' => $name,
            'city' => 'Abidjan',
            'neighborhood' => 'Cocody',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_available' => true,
        ]);
        $profile->forceFill(['status' => 'approved'])->save();
        $profile->services()->attach($service->id, ['price' => null, 'is_active' => true]);
    }

    $this->actingAs($client)
        ->post(route('client.services.providers.search', $service->slug), ['latitude' => 5.5, 'longitude' => -4])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locationRequired', false)
            ->where('providers.0.business_name', 'Proche')
            ->where('providers.1.business_name', 'Éloigné')
            ->has('providers.0.distance_km')
            ->missing('providers.0.latitude')
            ->missing('providers.0.longitude')
        );
});

test('le classement préfère une moyenne fiable fondée sur au moins trois avis visibles', function () {
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
    $category = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'is_active' => true]);
    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $createProfile = function (string $name, float $latitude) use ($service): ProviderProfile {
        $user = User::factory()->create(['role' => 'prestataire', 'email_verified_at' => now()]);
        $profile = $user->providerProfile()->create([
            'business_name' => $name,
            'city' => 'Abidjan',
            'neighborhood' => 'Cocody',
            'latitude' => $latitude,
            'longitude' => -4.0,
            'is_available' => true,
        ]);
        $profile->forceFill(['status' => 'approved'])->save();
        $profile->services()->attach($service->id, ['price' => null, 'is_active' => true]);

        return $profile;
    };

    $ratedProfile = $createProfile('Note fiable', 5.6);
    $newProfile = $createProfile('Aucun avis', 5.501);

    for ($index = 0; $index < 3; $index++) {
        $reviewer = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $request = new ServiceRequestModel([
            'description' => 'Travaux terminés et vérifiés par le client.',
            'city' => 'Abidjan',
            'neighborhood' => 'Cocody',
        ]);
        $request->client()->associate($reviewer);
        $request->providerProfile()->associate($ratedProfile);
        $request->service()->associate($service);
        $request->status = 'completed';
        $request->save();
        $review = new ServiceReview(['rating' => 5, 'comment' => 'Très bon travail.']);
        $review->client()->associate($reviewer);
        $review->providerProfile()->associate($ratedProfile);
        $request->review()->save($review);
    }

    $this->actingAs($client)
        ->post(route('client.services.providers.search', $service->slug), [
            'latitude' => 5.5,
            'longitude' => -4.0,
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('providers.0.id', $ratedProfile->id)
            ->where('providers.0.rating', 5)
            ->where('providers.0.review_count', 3)
            ->where('providers.1.id', $newProfile->id)
            ->where('providers.1.rating', null)
        );
});

test('guests must authenticate before searching providers', function () {
    $this->get('/client/services/reparation-robinet/providers')
        ->assertRedirect(route('login'));
});
