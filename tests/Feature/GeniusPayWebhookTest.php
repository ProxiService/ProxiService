<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceCommission;
use App\Models\ServicePayment;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use App\Notifications\ServicePaymentStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createGeniusPayWebhookFixture(): array
{
    config([
        'services.geniuspay.webhook_secret' => 'whsec_local_test_secret',
        'services.geniuspay.environment' => 'sandbox',
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
        'business_name' => 'Prestataire Webhook Test',
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

    $serviceRequest = new ServiceRequestModel([
        'description' => 'Réparation du robinet de la cuisine.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_urgent' => false,
    ]);

    $serviceRequest->client()->associate($client);
    $serviceRequest->providerProfile()->associate($profile);
    $serviceRequest->service()->associate($service);
    $serviceRequest->status = 'accepted';
    $serviceRequest->save();

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'provider_reference' => 'MTX-WEBHOOK-12345',
        'provider_transaction_id' => '98765',
        'amount' => 25000,
        'currency' => 'XOF',
        'status' => 'pending',
        'environment' => 'sandbox',
    ]);

    $payment->client()->associate($client);
    $serviceRequest->payments()->save($payment);

    return compact(
        'client',
        'provider',
        'profile',
        'service',
        'serviceRequest',
        'payment',
    );
}

function makeGeniusPayWebhookPayload(
    ServicePayment $payment,
    string $event = 'payment.success',
    string $status = 'completed',
    ?int $timestamp = null,
    array $dataOverrides = [],
    string $environment = 'sandbox',
): array {
    $timestamp ??= now()->timestamp;

    $data = array_replace([
        'object' => 'transaction',
        'id' => 98765,
        'reference' => $payment->provider_reference,
        'amount' => 25000,
        'currency' => 'XOF',
        'fees' => 500,
        'net_amount' => 24500,
        'status' => $status,
        'payment_method' => 'mobile_money',
        'provider' => 'wave',
        'metadata' => [
            'proxiservices_payment_id' => (string) $payment->id,
            'service_request_id' => (string) $payment->service_request_id,
        ],
    ], $dataOverrides);

    return [
        'id' => 'webhook-test-'.$payment->id.'-'.$event,
        'event' => $event,
        'timestamp' => $timestamp,
        'created_at' => now()->toISOString(),
        'data' => $data,
        'environment' => $environment,
        'api_version' => '2024-01-01',
    ];
}

function makeGeniusPayWebhookHeaders(
    array $payload,
    ?string $signature = null,
    ?string $headerEvent = null,
    ?string $environment = null,
    ?string $timestamp = null,
): array {
    $timestamp ??= (string) $payload['timestamp'];

    $signature ??= hash_hmac(
        'sha256',
        $timestamp.'.'.json_encode($payload),
        (string) config('services.geniuspay.webhook_secret'),
    );

    return [
        'X-Webhook-Signature' => $signature,
        'X-Webhook-Timestamp' => $timestamp,
        'X-Webhook-Event' => $headerEvent ?? $payload['event'],
        'X-Webhook-Environment' => $environment ?? $payload['environment'],
    ];
}

test('un webhook signé de paiement réussi confirme la tentative', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload($fixture['payment']);

    $this->postJson(
        route('webhooks.geniuspay'),
        $payload,
        makeGeniusPayWebhookHeaders($payload),
    )
        ->assertOk()
        ->assertJson([
            'success' => true,
            'result' => 'updated',
        ]);

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'completed',
        'provider_reference' => 'MTX-WEBHOOK-12345',
        'fees' => 500,
        'net_amount' => 24500,
        'payment_method' => 'mobile_money',
        'environment' => 'sandbox',
    ]);

    expect($fixture['payment']->fresh()->paid_at)->not->toBeNull();

    $this->assertDatabaseHas('service_requests', [
        'id' => $fixture['serviceRequest']->id,
        'status' => 'accepted',
    ]);

    expect(
        $fixture['client']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(1);

    expect(
        $fixture['provider']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(1);
});

test('un webhook avec une signature invalide reçoit une réponse 401', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload($fixture['payment']);

    $this->postJson(
        route('webhooks.geniuspay'),
        $payload,
        makeGeniusPayWebhookHeaders(
            $payload,
            signature: str_repeat('0', 64),
        ),
    )->assertUnauthorized();

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'pending',
    ]);
});

test('un webhook dont le timestamp date de plus de cinq minutes reçoit une réponse 400', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload(
        $fixture['payment'],
        timestamp: now()->timestamp - 301,
    );

    $this->postJson(
        route('webhooks.geniuspay'),
        $payload,
        makeGeniusPayWebhookHeaders($payload),
    )
        ->assertBadRequest()
        ->assertJsonPath('success', false);

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'pending',
    ]);
});

test('un événement qui ne correspond pas à son en-tête reçoit une réponse 400', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload($fixture['payment']);

    $this->postJson(
        route('webhooks.geniuspay'),
        $payload,
        makeGeniusPayWebhookHeaders(
            $payload,
            headerEvent: 'payment.failed',
        ),
    )->assertBadRequest();

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'pending',
    ]);
});

test('un montant différent de celui enregistré reçoit une réponse 422', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload(
        $fixture['payment'],
        dataOverrides: ['amount' => 1000],
    );

    $this->postJson(
        route('webhooks.geniuspay'),
        $payload,
        makeGeniusPayWebhookHeaders($payload),
    )->assertUnprocessable();

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'amount' => 25000,
        'status' => 'pending',
    ]);
});

test('un webhook d’un environnement différent reçoit une réponse 400', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload(
        $fixture['payment'],
        environment: 'live',
    );

    $this->postJson(
        route('webhooks.geniuspay'),
        $payload,
        makeGeniusPayWebhookHeaders($payload),
    )->assertBadRequest();

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'pending',
    ]);
});

test('un webhook de paiement échoué met à jour le statut', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload(
        $fixture['payment'],
        event: 'payment.failed',
        status: 'failed',
    );

    $this->postJson(
        route('webhooks.geniuspay'),
        $payload,
        makeGeniusPayWebhookHeaders($payload),
    )
        ->assertOk()
        ->assertJson([
            'success' => true,
            'result' => 'updated',
        ]);

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'failed',
    ]);

    expect(
        $fixture['client']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(1);

    expect(
        $fixture['provider']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(0);
});

test('le même webhook de réussite est traité de façon idempotente', function () {
    $fixture = createGeniusPayWebhookFixture();
    $payload = makeGeniusPayWebhookPayload($fixture['payment']);
    $headers = makeGeniusPayWebhookHeaders($payload);

    $this->postJson(route('webhooks.geniuspay'), $payload, $headers)
        ->assertOk()
        ->assertJsonPath('result', 'updated');

    $paidAt = $fixture['payment']->fresh()->paid_at;

    $this->postJson(route('webhooks.geniuspay'), $payload, $headers)
        ->assertOk()
        ->assertJsonPath('result', 'duplicate');

    expect($fixture['payment']->fresh()->paid_at->equalTo($paidAt))
        ->toBeTrue();
    $this->assertDatabaseCount('service_payments', 1);

    expect(
        $fixture['client']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(1);

    expect(
        $fixture['provider']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(1);
});

test('un webhook tardif d’échec ne fait pas régresser un paiement réussi', function () {
    $fixture = createGeniusPayWebhookFixture();
    $successPayload = makeGeniusPayWebhookPayload($fixture['payment']);

    $this->postJson(
        route('webhooks.geniuspay'),
        $successPayload,
        makeGeniusPayWebhookHeaders($successPayload),
    )->assertOk();

    $failurePayload = makeGeniusPayWebhookPayload(
        $fixture['payment'],
        event: 'payment.failed',
        status: 'failed',
    );

    $this->postJson(
        route('webhooks.geniuspay'),
        $failurePayload,
        makeGeniusPayWebhookHeaders($failurePayload),
    )
        ->assertOk()
        ->assertJsonPath('result', 'ignored');

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'completed',
    ]);
});

test('un webhook ne peut pas confirmer un paiement si le secret est absent', function () {
    config(['services.geniuspay.webhook_secret' => null]);

    $this->postJson(
        route('webhooks.geniuspay'),
        [],
    )
        ->assertServiceUnavailable()
        ->assertJsonPath('success', false);
});

test('un webhook de remboursement met à jour le statut et notifie les participants', function () {
    $fixture = createGeniusPayWebhookFixture();

    $commission = new ServiceCommission([
        'gross_amount' => 25000,
        'commission_rate' => 10,
        'commission_amount' => 2500,
        'provider_amount' => 22500,
        'collection_status' => 'pending',
        'calculated_at' => now(),
    ]);

    $fixture['serviceRequest']->commission()->save($commission);

    $successPayload = makeGeniusPayWebhookPayload(
        $fixture['payment'],
        event: 'payment.success',
        status: 'completed',
    );

    $this->postJson(
        route('webhooks.geniuspay'),
        $successPayload,
        makeGeniusPayWebhookHeaders($successPayload),
    )->assertOk();

    $refundPayload = makeGeniusPayWebhookPayload(
        $fixture['payment'],
        event: 'payment.refunded',
        status: 'refunded',
    );

    $this->postJson(
        route('webhooks.geniuspay'),
        $refundPayload,
        makeGeniusPayWebhookHeaders($refundPayload),
    )
        ->assertOk()
        ->assertJsonPath('result', 'updated');

    $reversedAt = $commission->fresh()->reversed_at;

    $this->postJson(
        route('webhooks.geniuspay'),
        $refundPayload,
        makeGeniusPayWebhookHeaders($refundPayload),
    )
        ->assertOk()
        ->assertJsonPath('result', 'duplicate');

    $this->assertDatabaseHas('service_payments', [
        'id' => $fixture['payment']->id,
        'status' => 'refunded',
    ]);

    $this->assertDatabaseHas('service_commissions', [
        'id' => $commission->id,
        'is_reversed' => true,
        'commission_amount' => 2500,
        'reversal_reason' => 'Paiement remboursé par GeniusPay.',
    ]);

    expect($reversedAt)->not->toBeNull();
    expect($commission->fresh()->reversed_at->equalTo($reversedAt))
        ->toBeTrue();

    expect(
        $fixture['client']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(2);

    expect(
        $fixture['provider']->notifications()
            ->where('type', ServicePaymentStatusNotification::class)
            ->count(),
    )->toBe(2);
});
