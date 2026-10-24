<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\ServiceReview;
use App\Models\ServiceReviewReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createReviewReportingTestFixture(): array
{
    $reviewer = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $reporter = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $profile = new ProviderProfile([
        'business_name' => 'Artisan Signalement',
        'bio' => 'Profil de test.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_available' => true,
    ]);

    $profile->user()->associate($provider);
    $profile->save();
    $profile->forceFill(['status' => 'approved'])->save();

    $category = Category::create([
        'name' => 'Plomberie',
        'slug' => 'plomberie',
        'description' => 'Travaux de plomberie.',
        'is_active' => true,
    ]);

    $service = Service::create([
        'category_id' => $category->id,
        'name' => 'Réparation de robinet',
        'slug' => 'reparation-robinet',
        'description' => 'Réparation de robinets.',
        'pricing_type' => 'quote',
        'is_active' => true,
    ]);

    $profile->services()->attach($service->id, [
        'price' => null,
        'is_active' => true,
    ]);

    $serviceRequest = new ServiceRequestModel([
        'description' => 'Réparation du robinet de la cuisine.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_urgent' => false,
    ]);

    $serviceRequest->client()->associate($reviewer);
    $serviceRequest->providerProfile()->associate($profile);
    $serviceRequest->service()->associate($service);
    $serviceRequest->status = 'completed';
    $serviceRequest->save();

    $review = new ServiceReview([
        'rating' => 2,
        'comment' => 'Avis de test.',
    ]);

    $review->client()->associate($reviewer);
    $review->providerProfile()->associate($profile);
    $serviceRequest->review()->save($review);

    return compact(
        'reviewer',
        'reporter',
        'provider',
        'profile',
        'service',
        'serviceRequest',
        'review',
    );
}

function createReviewReportForTest(
    ServiceReview $review,
    User $reporter,
): ServiceReviewReport {
    $report = new ServiceReviewReport([
        'reason' => 'abusive',
        'details' => 'Contenu à vérifier par la modération.',
    ]);

    $report->review()->associate($review);
    $report->reporter()->associate($reporter);
    $report->save();

    return $report;
}

test('un utilisateur peut signaler un avis avec un motif autorisé', function (string $reason) {
    $fixture = createReviewReportingTestFixture();

    $this->actingAs($fixture['reporter'])
        ->from(route('client.dashboard'))
        ->post(
            route('reviews.report.store', $fixture['review']),
            [
                'reason' => $reason,
                'details' => 'Ce commentaire semble injurieux.',
            ],
        )
        ->assertRedirect(route('client.dashboard'));

    $this->assertDatabaseHas('service_review_reports', [
        'service_review_id' => $fixture['review']->id,
        'reporter_id' => $fixture['reporter']->id,
        'reason' => $reason,
        'status' => 'pending',
    ]);
})->with([
    'spam' => 'spam',
    'contenu abusif' => 'abusive',
    'informations fausses' => 'false_information',
    'conflit d’intérêts' => 'conflict_of_interest',
    'autre problème' => 'other',
]);

test('un utilisateur ne peut pas fournir une raison non autorisée', function () {
    $fixture = createReviewReportingTestFixture();

    $this->actingAs($fixture['reporter'])
        ->from(route('client.dashboard'))
        ->post(
            route('reviews.report.store', $fixture['review']),
            ['reason' => 'made_up_reason'],
        )
        ->assertRedirect(route('client.dashboard'))
        ->assertSessionHasErrors('reason');

    $this->assertDatabaseCount('service_review_reports', 0);
});

test('un utilisateur ne peut pas signaler un avis masqué', function () {
    $fixture = createReviewReportingTestFixture();
    $fixture['review']->forceFill(['is_visible' => false])->save();

    $this->actingAs($fixture['reporter'])
        ->post(
            route('reviews.report.store', $fixture['review']),
            ['reason' => 'spam'],
        )
        ->assertNotFound();

    $this->assertDatabaseCount('service_review_reports', 0);
});

test('un visiteur doit se connecter pour signaler un avis', function () {
    $fixture = createReviewReportingTestFixture();

    $this->post(
        route('reviews.report.store', $fixture['review']),
        ['reason' => 'spam'],
    )->assertRedirect(route('login'));

    $this->assertDatabaseCount('service_review_reports', 0);
});

test('un client ne peut pas signaler son propre avis', function () {
    $fixture = createReviewReportingTestFixture();

    $this->actingAs($fixture['reviewer'])
        ->post(
            route('reviews.report.store', $fixture['review']),
            ['reason' => 'other'],
        )
        ->assertForbidden();

    $this->assertDatabaseCount('service_review_reports', 0);
});

test('un utilisateur ne peut pas signaler deux fois le même avis', function () {
    $fixture = createReviewReportingTestFixture();

    createReviewReportForTest(
        $fixture['review'],
        $fixture['reporter'],
    );

    $this->actingAs($fixture['reporter'])
        ->post(
            route('reviews.report.store', $fixture['review']),
            ['reason' => 'spam'],
        )
        ->assertStatus(409);

    $this->assertDatabaseCount('service_review_reports', 1);
});

test('un administrateur peut consulter les signalements en attente', function () {
    $fixture = createReviewReportingTestFixture();

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    createReviewReportForTest(
        $fixture['review'],
        $fixture['reporter'],
    );

    $this->actingAs($admin)
        ->get(route('admin.review-reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/review-reports/index')
            ->has('reports.data', 1)
            ->where(
                'reports.data.0.review.provider_name',
                'Artisan Signalement',
            )
        );
});

test('un administrateur peut rejeter un signalement sans masquer l’avis', function () {
    $fixture = createReviewReportingTestFixture();

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $report = createReviewReportForTest(
        $fixture['review'],
        $fixture['reporter'],
    );

    $this->actingAs($admin)
        ->patch(route('admin.review-reports.dismiss', $report))
        ->assertRedirect(route('admin.review-reports.index'));

    $this->assertDatabaseHas('service_review_reports', [
        'id' => $report->id,
        'status' => 'dismissed',
    ]);

    $this->assertDatabaseHas('service_reviews', [
        'id' => $fixture['review']->id,
        'is_visible' => true,
    ]);
});

test('un administrateur peut masquer un avis signalé', function () {
    $fixture = createReviewReportingTestFixture();

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $report = createReviewReportForTest(
        $fixture['review'],
        $fixture['reporter'],
    );

    $otherReporter = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);
    $otherReport = createReviewReportForTest(
        $fixture['review'],
        $otherReporter,
    );

    $this->actingAs($admin)
        ->patch(route('admin.review-reports.hide-review', $report))
        ->assertRedirect(route('admin.review-reports.index'));

    $this->assertDatabaseHas('service_reviews', [
        'id' => $fixture['review']->id,
        'is_visible' => false,
    ]);

    $this->assertDatabaseHas('service_review_reports', [
        'id' => $report->id,
        'status' => 'actioned',
    ]);
    $this->assertDatabaseHas('service_review_reports', [
        'id' => $otherReport->id,
        'status' => 'actioned',
    ]);

    $this->actingAs($fixture['reporter'])
        ->get(route('client.services.providers.show', [
            'service' => $fixture['service']->slug,
            'providerProfile' => $fixture['profile']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reviewSummary.average', null)
            ->where('reviewSummary.count', 0)
            ->has('reviews', 0)
        );
});

test('un client ne peut pas modérer les signalements', function () {
    $fixture = createReviewReportingTestFixture();

    $report = createReviewReportForTest(
        $fixture['review'],
        $fixture['reporter'],
    );

    $this->actingAs($fixture['reporter'])
        ->patch(route('admin.review-reports.dismiss', $report))
        ->assertForbidden();

    $this->assertDatabaseHas('service_review_reports', [
        'id' => $report->id,
        'status' => 'pending',
    ]);
});
