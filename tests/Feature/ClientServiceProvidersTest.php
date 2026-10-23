<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
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
        ->get('/client/services/reparation-robinet/providers?city=Anyama')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/services/providers')
            ->where('service.slug', 'reparation-robinet')
            ->where('filters.city', 'Anyama')
            ->has('providers', 1)
            ->where('providers.0.business_name', 'Jean Plomberie')
            ->where('providers.0.city', 'Anyama')
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

test('guests must authenticate before searching providers', function () {
    $this->get('/client/services/reparation-robinet/providers')
        ->assertRedirect(route('login'));
});
