<?php

use Database\Seeders\DatabaseSeeder;

test('database seeder can be run more than once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});
