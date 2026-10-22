<?php

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('clients can search for an existing service', function () {
    $client = User::factory()->create();

    $client->forceFill([
        'role' => 'client',
        'email_verified_at' => now(),
    ])->save();

    $category = Category::create([
        'name' => 'Plomberie',
        'slug' => 'plomberie',
        'description' => 'Réparation et installation de plomberie.',
        'icon' => 'wrench',
        'is_active' => true,
    ]);

    Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'description' => 'Réparation ou remplacement simple de robinet.',
        'pricing_type' => 'fixed',
        'base_price' => 5000,
        'estimated_duration_minutes' => 45,
        'is_active' => true,
    ]);

    $this->actingAs($client)
        ->get('/client/search?q=robinet')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/search/index')
            ->where('searchTerm', 'robinet')
            ->has('services', 1)
            ->where('services.0.name', 'Réparation de robinet')
            ->where('services.0.category.name', 'Plomberie')
        );
});
