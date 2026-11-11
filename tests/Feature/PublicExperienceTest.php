<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makePublicLocationFixture(): array
{
    $provider = User::factory()->create(['role' => 'prestataire', 'email_verified_at' => now()]);
    $category = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'description' => 'Services de plomberie.', 'is_active' => true]);
    $service = Service::create(['category_id' => $category->id, 'name' => 'Réparation de robinet', 'slug' => 'reparation-robinet', 'description' => 'Réparation de fuite.', 'pricing_type' => 'quote', 'is_active' => true]);
    $profile = new ProviderProfile(['business_name' => 'Artisan de test', 'bio' => 'Profil de test.', 'city' => 'Yopougon', 'neighborhood' => 'Niangon', 'is_available' => true, 'latitude' => 5.35, 'longitude' => -4.1]);
    $profile->user()->associate($provider);
    $profile->save();
    $profile->forceFill(['status' => 'approved'])->save();
    $profile->services()->attach($service);

    return compact('provider', 'category', 'service', 'profile');
}

test('la page d’accueil publique présente la landing française', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
        );
});

test('les pages légales affichent un avertissement et des champs à compléter', function (string $slug, string $title) {
    $this->get("/informations/{$slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('legal/show')
            ->where('title', $title)
            ->has('sections')
        );
})->with([
    ['cgu', 'Conditions d’utilisation'],
    ['confidentialite', 'Politique de confidentialité'],
    ['mentions-legales', 'Mentions légales'],
    ['paiements-remboursements', 'Paiements et remboursements'],
]);

test('le plan du site public ne référence que des pages publiques', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/informations/confidentialite')
        ->assertDontSee('/client/');
});

test('robots.txt fournit un sitemap absolu et exclut les espaces privés', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: http://localhost/sitemap.xml')
        ->assertSee('Disallow: /admin/');
});

test('la page SEO service et commune ne sort que pour une offre active réelle', function () {
    $fixture = makePublicLocationFixture();
    $this->get('/services/reparation-robinet/a/yopougon')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/service-location')
            ->where('service.name', 'Réparation de robinet')
            ->where('zone', 'Yopougon')
        );

    $this->get('/services/reparation-robinet/a/cocody')->assertNotFound();
});

test('le sitemap inclut les zones réelles couvertes par les profils actifs', function () {
    makePublicLocationFixture();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/services/reparation-robinet/a/yopougon');
});

test('une page d’information non prévue retourne 404', function () {
    $this->get('/informations/texte-inconnu')->assertNotFound();
});
