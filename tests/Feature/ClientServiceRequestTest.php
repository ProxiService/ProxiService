<?php

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewServiceRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('clients can submit a request to an approved provider', function () {
    $client = User::factory()->create();

    $client->forceFill([
        'role' => 'client',
        'email_verified_at' => now(),
    ])->save();

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
        'description' => 'Réparation de robinet.',
        'pricing_type' => 'fixed',
        'base_price' => 5000,
        'estimated_duration_minutes' => 45,
        'is_active' => true,
    ]);

    $profile = $provider->providerProfile()->create([
        'business_name' => 'Jean Plomberie',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
    ]);

    $profile->forceFill([
        'status' => 'approved',
    ])->save();

    $profile->services()->attach($service->id, [
        'price' => 5000,
        'is_active' => true,
    ]);

    $url = "/client/services/{$service->slug}/providers/{$profile->id}/request";

    Notification::fake();

    $this->actingAs($client)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/requests/create')
            ->where('service.name', 'Réparation de robinet')
            ->where('provider.business_name', 'Jean Plomberie')
        );

    $this->actingAs($client)
        ->post($url, [
            'description' => 'Le robinet de ma cuisine fuit beaucoup.',
            'city' => 'Anyama',
            'neighborhood' => 'Centre',
            'address' => 'Près du marché',
            'scheduled_at' => null,
            'is_urgent' => true,

            // Ces champs envoyés frauduleusement doivent être ignorés.
            'status' => 'completed',
            'client_id' => $provider->id,
        ])
        ->assertRedirect(route('client.dashboard'));

    Notification::assertSentTo(
        $provider,
        NewServiceRequestNotification::class,
        fn (NewServiceRequestNotification $notification): bool => $notification->serviceName === $service->name
            && $notification->clientName === $client->name,
    );

    $this->assertDatabaseHas('service_requests', [
        'client_id' => $client->id,
        'provider_profile_id' => $profile->id,
        'service_id' => $service->id,
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_urgent' => 1,
        'status' => 'pending',
    ]);

    $this->actingAs($client)
        ->from($url)
        ->post($url, [
            'description' => 'Contacte-moi au +225 07 12 34 56 78 pour le devis.',
            'city' => 'Anyama',
            'neighborhood' => 'Centre',
        ])
        ->assertSessionHasErrors('description');

    $this->assertDatabaseCount('service_requests', 1);
});

test('clients cannot request a service from an unapproved provider', function () {
    $client = User::factory()->create();

    $client->forceFill([
        'role' => 'client',
        'email_verified_at' => now(),
    ])->save();

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
        'description' => 'Réparation de robinet.',
        'pricing_type' => 'fixed',
        'base_price' => 5000,
        'estimated_duration_minutes' => 45,
        'is_active' => true,
    ]);

    $profile = $provider->providerProfile()->create([
        'business_name' => 'Prestataire en attente',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
    ]);

    $profile->services()->attach($service->id, [
        'price' => 5000,
        'is_active' => true,
    ]);

    $url = "/client/services/{$service->slug}/providers/{$profile->id}/request";

    $this->actingAs($client)
        ->post($url, [
            'description' => 'Je souhaite une réparation de robinet.',
            'city' => 'Anyama',
            'neighborhood' => 'Centre',
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('service_requests', 0);
});
