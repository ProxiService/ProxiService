<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('admins can view pending provider profiles', function () {
    $admin = User::factory()->create();

    $admin->forceFill([
        'role' => 'admin',
        'email_verified_at' => now(),
    ])->save();

    $provider = User::factory()->create();

    $provider->forceFill([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ])->save();

    $provider->providerProfile()->create([
        'business_name' => 'Jean Plomberie',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.providers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/providers/index')
            ->has('profiles.data', 1)
            ->where('profiles.data.0.business_name', 'Jean Plomberie')
            ->where('profiles.data.0.user.email', $provider->email)
        );
});

test('admins can approve a pending provider profile', function () {
    $admin = User::factory()->create();

    $admin->forceFill([
        'role' => 'admin',
        'email_verified_at' => now(),
    ])->save();

    $provider = User::factory()->create();

    $provider->forceFill([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ])->save();

    $profile = $provider->providerProfile()->create([
        'business_name' => 'Jean Plomberie',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.providers.approve', $profile))
        ->assertRedirect(route('admin.providers.index'));

    $this->assertDatabaseHas('provider_profiles', [
        'id' => $profile->id,
        'status' => 'approved',
    ]);
});

test('admins can reject a pending provider profile', function () {
    $admin = User::factory()->create();

    $admin->forceFill([
        'role' => 'admin',
        'email_verified_at' => now(),
    ])->save();

    $provider = User::factory()->create();

    $provider->forceFill([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ])->save();

    $profile = $provider->providerProfile()->create([
        'business_name' => 'Profil à examiner',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.providers.reject', $profile))
        ->assertRedirect(route('admin.providers.index'));

    $this->assertDatabaseHas('provider_profiles', [
        'id' => $profile->id,
        'status' => 'rejected',
    ]);
});

test('clients cannot access provider review or approve profiles', function () {
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

    $profile = $provider->providerProfile()->create([
        'business_name' => 'Profil test',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
    ]);

    $this->actingAs($client)
        ->get(route('admin.providers.index'))
        ->assertForbidden();

    $this->actingAs($client)
        ->patch(route('admin.providers.approve', $profile))
        ->assertForbidden();

    $this->assertDatabaseHas('provider_profiles', [
        'id' => $profile->id,
        'status' => 'pending',
    ]);
});
