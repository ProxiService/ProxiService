<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoMarketplaceSeeder;

test('database seeder can be run more than once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 5);
    $this->assertDatabaseHas('users', [
        'name' => 'Client Démo',
        'email' => 'client.demo@example.test',
    ]);
    $this->assertDatabaseCount('provider_profiles', 4);
    $this->assertDatabaseCount('service_reviews', 4);
    $this->assertDatabaseHas('service_reviews', [
        'comment' => '[AVIS DE DÉMONSTRATION — FICTIF] Exemple neutre, sans expérience client réelle.',
    ]);
});

test('demo marketplace seeder refuses production environment', function () {
    $this->app['env'] = 'production';

    $this->seed(DemoMarketplaceSeeder::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('provider_profiles', 0);
});
