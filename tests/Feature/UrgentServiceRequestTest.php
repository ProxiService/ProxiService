<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\UrgentRequestDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function urgentFixtureProfile(Service $service, string $name, float $latitude, bool $available = true): array
{
    $user = User::factory()->create(['role' => 'prestataire', 'email_verified_at' => now()]);
    $profile = new ProviderProfile([
        'business_name' => $name, 'city' => 'Abidjan', 'neighborhood' => 'Centre',
        'latitude' => $latitude, 'longitude' => 0, 'is_available' => $available,
    ]);
    $profile->user()->associate($user);
    $profile->save();
    $profile->forceFill(['status' => 'approved'])->save();
    $profile->services()->attach($service->id, ['is_active' => true, 'price' => 10000]);

    return [$user, $profile];
}

function urgentServiceFixture(): array
{
    $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
    $category = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'is_active' => true]);
    $service = Service::create([
        'category_id' => $category->id, 'name' => 'Fuite d’eau', 'slug' => 'fuite-eau',
        'pricing_type' => 'quote', 'is_active' => true,
    ]);

    return [$client, $service];
}

function createUrgentRequest(User $client, Service $service, array $attributes = []): ServiceRequest
{
    $request = new ServiceRequest;
    $request->forceFill(array_merge([
        'client_id' => $client->id,
        'provider_profile_id' => null,
        'service_id' => $service->id,
        'description' => 'Une fuite importante dans la cuisine.',
        'city' => 'Abidjan', 'neighborhood' => 'Centre', 'is_urgent' => true,
        'status' => 'pending', 'urgent_latitude' => 0, 'urgent_longitude' => 0,
        'urgent_expires_at' => now()->addMinutes(15), 'urgent_dispatch_status' => 'searching',
    ], $attributes));
    $request->save();

    return $request;
}

test('une urgence exige une position GPS et un consentement explicite', function () {
    [$client, $service] = urgentServiceFixture();
    $url = route('client.requests.urgent.store');

    $this->actingAs($client)->post($url, [
        'service_id' => $service->id, 'description' => 'Une fuite importante dans la cuisine.',
        'city' => 'Abidjan', 'neighborhood' => 'Centre', 'location_consent' => false,
        'latitude' => '', 'longitude' => '',
    ])->assertSessionHasErrors(['location_consent', 'latitude', 'longitude']);

    expect(ServiceRequest::query()->count())->toBe(0);
});

test('la diffusion initiale cible les prestataires approuvés disponibles dans un rayon de cinq kilomètres', function () {
    [$client, $service] = urgentServiceFixture();
    [, $near] = urgentFixtureProfile($service, 'Proche', 0.03);
    [, $far] = urgentFixtureProfile($service, 'Hors rayon', 0.07);
    [, $unavailable] = urgentFixtureProfile($service, 'Indisponible', 0.01, false);
    [, $unapproved] = urgentFixtureProfile($service, 'Non approuvé', 0.01);
    $unapproved->forceFill(['status' => 'pending'])->save();
    [, $withoutGps] = urgentFixtureProfile($service, 'Sans GPS', 0.01);
    $withoutGps->forceFill(['latitude' => null, 'longitude' => null])->save();

    $this->actingAs($client)->post(route('client.requests.urgent.store'), [
        'service_id' => $service->id, 'description' => 'Une fuite importante dans la cuisine.',
        'city' => 'Abidjan', 'neighborhood' => 'Centre', 'location_consent' => true,
        'latitude' => 0, 'longitude' => 0,
    ])->assertRedirect(route('client.requests.index'));

    $request = ServiceRequest::query()->firstOrFail();
    $this->assertDatabaseHas('urgent_service_request_matches', [
        'service_request_id' => $request->id, 'provider_profile_id' => $near->id, 'matched_radius_km' => 5,
    ]);
    $this->assertDatabaseMissing('urgent_service_request_matches', ['service_request_id' => $request->id, 'provider_profile_id' => $far->id]);
    $this->assertDatabaseMissing('urgent_service_request_matches', ['service_request_id' => $request->id, 'provider_profile_id' => $unavailable->id]);
    $this->assertDatabaseMissing('urgent_service_request_matches', ['service_request_id' => $request->id, 'provider_profile_id' => $unapproved->id]);
    $this->assertDatabaseMissing('urgent_service_request_matches', ['service_request_id' => $request->id, 'provider_profile_id' => $withoutGps->id]);
});

test('le rayon passe à dix kilomètres après cinq minutes et seul le premier prestataire peut accepter avec son devis', function () {
    [$client, $service] = urgentServiceFixture();
    [$provider, $profile] = urgentFixtureProfile($service, 'Second cercle', 0.07);
    [$otherProvider, $otherProfile] = urgentFixtureProfile($service, 'Concurrent', 0.07);
    $request = createUrgentRequest($client, $service);
    app(UrgentRequestDispatcher::class)->dispatch($request->load('service'), 5);
    expect(DB::table('urgent_service_request_matches')->where('service_request_id', $request->id)->count())->toBe(0);

    $this->travel(5)->minutes();
    app(UrgentRequestDispatcher::class)->dispatchDue();
    $this->assertDatabaseHas('urgent_service_request_matches', [
        'service_request_id' => $request->id, 'provider_profile_id' => $profile->id, 'matched_radius_km' => 10,
    ]);

    $this->actingAs($provider)->post(route('prestataire.requests.accept-urgent', $request), [
        'amount' => 25000, 'description' => 'Réparation de la fuite et contrôle des raccords.',
    ])->assertRedirect(route('prestataire.requests.index'));
    $this->actingAs($otherProvider)->post(route('prestataire.requests.accept-urgent', $request), [
        'amount' => 26000, 'description' => 'Réparation de la fuite et contrôle des raccords.',
    ])->assertStatus(409);

    $this->assertDatabaseHas('service_requests', [
        'id' => $request->id, 'status' => 'accepted', 'provider_profile_id' => $profile->id,
        'urgent_latitude' => null, 'urgent_longitude' => null,
    ]);
    $this->assertDatabaseCount('service_quotes', 1);
    $this->assertDatabaseMissing('service_quotes', ['provider_profile_id' => $otherProfile->id]);
});

test('une urgence sans candidat expire sans être marquée comme attribuée', function () {
    [$client, $service] = urgentServiceFixture();
    $request = createUrgentRequest($client, $service);
    app(UrgentRequestDispatcher::class)->dispatch($request->load('service'), 5);
    $this->travel(15)->minutes();
    app(UrgentRequestDispatcher::class)->dispatchDue();

    $this->assertDatabaseHas('service_requests', [
        'id' => $request->id, 'status' => 'expired', 'urgent_dispatch_status' => 'no_candidates',
        'urgent_latitude' => null, 'urgent_longitude' => null,
    ]);
});

test('une demande urgente ne peut pas démarrer de checkout', function () {
    [$client, $service] = urgentServiceFixture();
    [, $profile] = urgentFixtureProfile($service, 'Attribué', 0.01);
    $request = createUrgentRequest($client, $service, [
        'status' => 'accepted', 'provider_profile_id' => $profile->id,
        'urgent_dispatch_status' => 'assigned',
    ]);

    $this->actingAs($client)->post(route('client.requests.payments.store', $request))
        ->assertStatus(409);
    $this->assertDatabaseCount('service_payments', 0);
});

test('les policies autorisent seulement le prestataire ciblé à consulter une urgence et aucune coordonnée GPS ne lui est transmise', function () {
    [$client, $service] = urgentServiceFixture();
    [$provider, $profile] = urgentFixtureProfile($service, 'Candidat', 0.01);
    [$otherProvider] = urgentFixtureProfile($service, 'Autre', 0.02);
    $request = createUrgentRequest($client, $service);
    DB::table('urgent_service_request_matches')->insert([
        'service_request_id' => $request->id, 'provider_profile_id' => $profile->id,
        'matched_radius_km' => 5, 'notified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(Gate::forUser($provider)->allows('view', $request))->toBeTrue()
        ->and(Gate::forUser($otherProvider)->allows('view', $request))->toBeFalse();

    $response = $this->actingAs($provider)->get(route('prestataire.requests.index'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('prestataire/requests/index')->has('requests.data', 1)
        ->where('requests.data.0.id', $request->id));
    expect($response->getContent())->not->toContain('urgent_latitude')->not->toContain('urgent_longitude');
});
