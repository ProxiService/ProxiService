<?php

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('providers can create and update their professional profile', function () {
    $provider = User::factory()->create();

    $provider->forceFill([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ])->save();

    $this->actingAs($provider)
        ->get(route('prestataire.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('prestataire/profile/edit')
            ->where('profile', null)
        );

    $this->actingAs($provider)
        ->put(route('prestataire.profile.update'), [
            'business_name' => 'Jean Plomberie',
            'bio' => 'Réparation et installation sanitaire.',
            'phone' => '0700000000',
            'years_experience' => 5,
            'city' => 'Anyama',
            'neighborhood' => 'Centre',
            'address' => null,
            'status' => 'approved',
        ])
        ->assertRedirect(route('prestataire.profile.edit'));

    $this->assertDatabaseHas('provider_profiles', [
        'user_id' => $provider->id,
        'business_name' => 'Jean Plomberie',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'status' => 'pending',
    ]);
});

test('clients cannot access the provider profile page', function () {
    $client = User::factory()->create();

    $client->forceFill([
        'role' => 'client',
        'email_verified_at' => now(),
    ])->save();

    $this->actingAs($client)
        ->get(route('prestataire.profile.edit'))
        ->assertForbidden();
});

test('providers can select services for their profile', function () {
    $provider = User::factory()->create();

    $provider->forceFill([
        'role' => 'prestataire',
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
        'description' => 'Réparation simple de robinet.',
        'pricing_type' => 'fixed',
        'base_price' => 5000,
        'estimated_duration_minutes' => 45,
        'is_active' => true,
    ]);

    $this->actingAs($provider)
        ->put(route('prestataire.profile.update'), [
            'business_name' => 'Jean Plomberie',
            'city' => 'Anyama',
            'neighborhood' => 'Centre',
            'services' => [$service->id],
        ])
        ->assertRedirect(route('prestataire.profile.edit'));

    $profile = $provider->providerProfile()->firstOrFail();

    $this->assertDatabaseHas('provider_profile_service', [
        'provider_profile_id' => $profile->id,
        'service_id' => $service->id,
    ]);
});
