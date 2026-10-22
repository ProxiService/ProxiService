<?php

use App\Models\User;

test('guests cannot access role dashboards', function () {
    $this->get(route('client.dashboard'))
        ->assertRedirect(route('login'));

    $this->get(route('prestataire.dashboard'))
        ->assertRedirect(route('login'));

    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('users are redirected to the dashboard for their role', function () {
    $client = User::factory()->create();
    $client->forceFill(['role' => 'client'])->save();

    $provider = User::factory()->create();
    $provider->forceFill(['role' => 'prestataire'])->save();

    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin'])->save();

    $this->actingAs($client)
        ->get(route('dashboard'))
        ->assertRedirect(route('client.dashboard'));

    $this->actingAs($provider)
        ->get(route('dashboard'))
        ->assertRedirect(route('prestataire.dashboard'));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('clients cannot access provider or admin dashboards', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'role' => 'client',
        'email_verified_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->get(route('client.dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('prestataire.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('providers cannot access client or admin dashboards', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->get(route('prestataire.dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('client.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admins cannot access client or provider dashboards', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'role' => 'admin',
        'email_verified_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('client.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('prestataire.dashboard'))
        ->assertForbidden();
});
