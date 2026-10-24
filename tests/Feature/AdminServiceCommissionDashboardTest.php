<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceCommission;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createAdminFinanceDashboardFixture(): array
{
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $client = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $provider = User::factory()->create([
        'role' => 'prestataire',
        'email_verified_at' => now(),
    ]);

    $profile = new ProviderProfile([
        'business_name' => 'Prestataire Tableau Financier',
        'bio' => 'Profil créé pour les tests.',
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

    return compact(
        'admin',
        'client',
        'provider',
        'profile',
        'category',
        'service',
    );
}

function addAdminFinanceCommission(
    array $fixture,
    float $grossAmount,
    float $rate,
    string $collectionStatus = 'pending',
): ServiceCommission {
    $serviceRequest = new ServiceRequestModel([
        'description' => 'Prestation créée pour les tests financiers.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_urgent' => false,
    ]);

    $serviceRequest->client()->associate($fixture['client']);
    $serviceRequest->providerProfile()->associate($fixture['profile']);
    $serviceRequest->service()->associate($fixture['service']);
    $serviceRequest->status = 'completed';
    $serviceRequest->save();

    $commissionAmount = round($grossAmount * $rate / 100, 2);

    $commission = new ServiceCommission([
        'gross_amount' => $grossAmount,
        'commission_rate' => $rate,
        'commission_amount' => $commissionAmount,
        'provider_amount' => round($grossAmount - $commissionAmount, 2),
        'collection_status' => $collectionStatus,
        'calculated_at' => now(),
    ]);

    $serviceRequest->commission()->save($commission);

    return $commission;
}

test('un administrateur peut consulter les totaux et les détails financiers', function () {
    $fixture = createAdminFinanceDashboardFixture();

    addAdminFinanceCommission(
        $fixture,
        grossAmount: 25000,
        rate: 10,
        collectionStatus: 'pending',
    );

    addAdminFinanceCommission(
        $fixture,
        grossAmount: 18000,
        rate: 10,
        collectionStatus: 'collected',
    );

    $this->actingAs($fixture['admin'])
        ->get(route('admin.commissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/commissions/index', false)
            ->where('summary.total_commissions', 2)
            ->where('summary.gross_total', 43000)
            ->where('summary.commission_total', 4300)
            ->where('summary.provider_total', 38700)
            ->where('summary.pending_total', 2500)
            ->has('commissions.data', 2)
            ->where(
                'commissions.data.0.provider_name',
                'Prestataire Tableau Financier',
            )
            ->where(
                'commissions.data.0.service_name',
                'Réparation de robinet',
            )
        );
});

test('un client ne peut pas accéder au tableau de bord financier', function () {
    $fixture = createAdminFinanceDashboardFixture();

    $this->actingAs($fixture['client'])
        ->get(route('admin.commissions.index'))
        ->assertForbidden();
});

test('le tableau financier affiche des totaux nuls lorsqu’aucune commission n’existe', function () {
    $fixture = createAdminFinanceDashboardFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('admin.commissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/commissions/index', false)
            ->where('summary.total_commissions', 0)
            ->where('summary.gross_total', 0)
            ->where('summary.commission_total', 0)
            ->where('summary.provider_total', 0)
            ->where('summary.pending_total', 0)
            ->has('commissions.data', 0)
        );
});
