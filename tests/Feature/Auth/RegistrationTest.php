<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register as clients', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'role' => 'client',
    ]);
});

test('new users can access the dashboard without email verification locally', function () {
    config(['fortify.email_verification_required' => false]);
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Local User',
        'email' => 'local@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'local@example.com')->firstOrFail();

    $this->assertAuthenticated();
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('client.dashboard'));
    Notification::assertNothingSentTo($user, VerifyEmail::class);
});

test('users cannot register as administrators', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Malicious User',
        'email' => 'attempt-admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'attempt-admin@example.com',
        'role' => 'client',
    ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'attempt-admin@example.com',
        'role' => 'admin',
    ]);
});
