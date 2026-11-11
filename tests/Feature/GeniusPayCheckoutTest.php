<?php

use App\Contracts\PaymentGateway;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServicePayment;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use App\Services\GeniusPayCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('la passerelle GeniusPay est fournie derrière le contrat de paiement', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(GeniusPayCheckoutService::class);
});

function createGeniusPayCheckoutFixture(
    string $pricingType = 'quote',
    bool $withAcceptedQuote = true,
): array {
    config([
        'services.geniuspay.base_url' => 'https://geniuspay.ci/api/v1/merchant',
        'services.geniuspay.api_key' => 'pk_sandbox_test',
        'services.geniuspay.api_secret' => 'sk_sandbox_test',
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
        'business_name' => 'Artisan Checkout Test',
        'bio' => 'Prestataire créé pour les tests.',
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
        'pricing_type' => $pricingType,
        'base_price' => $pricingType === 'fixed' ? 12000 : null,
        'is_active' => true,
    ]);

    $profile->services()->attach($service->id, [
        'price' => $pricingType === 'fixed' ? 15000 : null,
        'is_active' => true,
    ]);

    $serviceRequest = new ServiceRequestModel([
        'description' => 'Réparation du robinet de ma cuisine.',
        'city' => 'Anyama',
        'neighborhood' => 'Centre',
        'is_urgent' => false,
    ]);

    $serviceRequest->client()->associate($client);
    $serviceRequest->providerProfile()->associate($profile);
    $serviceRequest->service()->associate($service);
    $serviceRequest->status = 'accepted';
    $serviceRequest->save();

    if ($pricingType === 'quote' && $withAcceptedQuote) {
        $quote = new ServiceQuote([
            'amount' => 25000,
            'description' => 'Réparation complète et remplacement des pièces.',
        ]);

        $quote->providerProfile()->associate($profile);
        $serviceRequest->quotes()->save($quote);

        $quote->status = 'accepted';
        $quote->responded_at = now();
        $quote->save();
    }

    return compact(
        'client',
        'provider',
        'profile',
        'service',
        'serviceRequest',
    );
}

function fakeGeniusPayCheckout(float $amount = 25000): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'success' => true,
            'data' => [
                'id' => 12345,
                'reference' => 'MTX-SANDBOX-12345',
                'amount' => $amount,
                'currency' => 'XOF',
                'fees' => 500,
                'net_amount' => $amount - 500,
                'status' => 'pending',
                'gateway' => 'wave',
                'checkout_url' => 'https://checkout.geniuspay.ci/pay/test-12345',
                'environment' => 'sandbox',
                'metadata' => [
                    'service_request_id' => '1',
                ],
            ],
        ], 201),
    ]);
}

test('le client peut obtenir un checkout pour un devis accepté', function () {
    $fixture = createGeniusPayCheckoutFixture();

    fakeGeniusPayCheckout();

    $this->withHeaders(['X-Inertia' => 'true'])
        ->actingAs($fixture['client'])
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertConflict()
        ->assertHeader(
            'X-Inertia-Location',
            'https://checkout.geniuspay.ci/pay/test-12345',
        );

    $payment = ServicePayment::query()->firstOrFail();

    expect((float) $payment->amount)->toBe(25000.0);
    expect($payment->provider)->toBe('geniuspay');
    expect($payment->environment)->toBe('sandbox');

    $this->assertDatabaseHas('service_payments', [
        'id' => $payment->id,
        'service_request_id' => $fixture['serviceRequest']->id,
        'client_id' => $fixture['client']->id,
        'provider_reference' => 'MTX-SANDBOX-12345',
        'provider_transaction_id' => '12345',
        'amount' => 25000,
        'currency' => 'XOF',
        'status' => 'pending',
        'environment' => 'sandbox',
    ]);

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/payments')
        && (float) $request['amount'] === 25000.0
        && $request['currency'] === 'XOF'
        && $request['customer']['country'] === 'CI'
        && $request->hasHeader('X-API-Key', 'pk_sandbox_test')
        && $request->hasHeader('X-API-Secret', 'sk_sandbox_test')
    );
});

test('un service à prix fixe utilise le tarif du prestataire', function () {
    $fixture = createGeniusPayCheckoutFixture(
        pricingType: 'fixed',
        withAcceptedQuote: false,
    );

    fakeGeniusPayCheckout(15000);

    $this->actingAs($fixture['client'])
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(
            'https://checkout.geniuspay.ci/pay/test-12345',
        );

    $this->assertDatabaseHas('service_payments', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'amount' => 15000,
        'status' => 'pending',
    ]);
});

test('le client ne peut pas payer la demande d’un autre client', function () {
    $fixture = createGeniusPayCheckoutFixture();
    Http::preventStrayRequests();

    $otherClient = User::factory()->create([
        'role' => 'client',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($otherClient)
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertNotFound();

    $this->assertDatabaseCount('service_payments', 0);
    Http::assertNothingSent();
});

test('aucun checkout ne peut être créé sans devis accepté', function () {
    $fixture = createGeniusPayCheckoutFixture(
        pricingType: 'quote',
        withAcceptedQuote: false,
    );
    Http::preventStrayRequests();

    $this->actingAs($fixture['client'])
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertStatus(409);

    $this->assertDatabaseCount('service_payments', 0);
    Http::assertNothingSent();
});

test('une tentative avec checkout actif est réutilisée', function () {
    $fixture = createGeniusPayCheckoutFixture();

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'provider_reference' => 'MTX-EXISTING-123',
        'provider_transaction_id' => '999',
        'amount' => 25000,
        'currency' => 'XOF',
        'status' => 'pending',
        'checkout_url' => 'https://checkout.geniuspay.ci/pay/existing',
        'environment' => 'sandbox',
    ]);

    $payment->client()->associate($fixture['client']);
    $fixture['serviceRequest']->payments()->save($payment);

    Http::preventStrayRequests();

    $this->actingAs($fixture['client'])
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(
            'https://checkout.geniuspay.ci/pay/existing',
        );

    $this->assertDatabaseCount('service_payments', 1);
    Http::assertNothingSent();
});

test('une erreur GeniusPay est enregistrée comme échec sans déclarer le paiement réussi', function () {
    $fixture = createGeniusPayCheckoutFixture();
    Http::preventStrayRequests();
    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'success' => false,
            'error' => [
                'code' => 'PAYMENT_INIT_FAILED',
                'message' => 'Échec simulé du checkout.',
            ],
        ], 400),
    ]);

    $this->actingAs($fixture['client'])
        ->from(route('client.requests.index'))
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('client.requests.index'))
        ->assertSessionHasErrors('payment');

    $this->assertDatabaseHas('service_payments', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'status' => 'failed',
        'failure_reason' => 'Impossible d’initialiser le checkout GeniusPay.',
    ]);
});

test('une réponse avec un montant incohérent échoue sans rediriger le client', function () {
    $fixture = createGeniusPayCheckoutFixture();
    Http::preventStrayRequests();
    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SANDBOX-12345',
                'amount' => 30000,
                'currency' => 'XOF',
                'checkout_url' => 'https://checkout.geniuspay.ci/pay/test-12345',
                'environment' => 'sandbox',
            ],
        ], 201),
    ]);

    $this->actingAs($fixture['client'])
        ->from(route('client.requests.index'))
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('client.requests.index'))
        ->assertSessionHasErrors('payment');

    $this->assertDatabaseHas('service_payments', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'status' => 'failed',
        'checkout_url' => null,
    ]);
});

test('une URL de checkout externe à GeniusPay est rejetée', function () {
    $fixture = createGeniusPayCheckoutFixture();
    Http::preventStrayRequests();
    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'success' => true,
            'data' => [
                'reference' => 'MTX-SANDBOX-12345',
                'amount' => 25000,
                'currency' => 'XOF',
                'checkout_url' => 'https://attacker.example/checkout',
                'environment' => 'sandbox',
            ],
        ], 201),
    ]);

    $this->actingAs($fixture['client'])
        ->from(route('client.requests.index'))
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(route('client.requests.index'))
        ->assertSessionHasErrors('payment');

    $this->assertDatabaseHas('service_payments', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'status' => 'failed',
        'checkout_url' => null,
    ]);
});

test('un retour navigateur ne confirme pas un paiement', function () {
    $fixture = createGeniusPayCheckoutFixture();

    $payment = new ServicePayment([
        'provider' => 'geniuspay',
        'amount' => 25000,
        'currency' => 'XOF',
        'status' => 'pending',
        'environment' => 'sandbox',
    ]);

    $payment->client()->associate($fixture['client']);
    $fixture['serviceRequest']->payments()->save($payment);

    $this->actingAs($fixture['client'])
        ->get(route('client.payments.return'))
        ->assertRedirect(route('client.requests.index'));

    $this->assertDatabaseHas('service_payments', [
        'id' => $payment->id,
        'status' => 'pending',
    ]);
});

test('le checkout accepte aussi payment_url dans la réponse GeniusPay', function () {
    $fixture = createGeniusPayCheckoutFixture();

    Http::fake([
        '*api/v1/merchant/payments' => Http::response([
            'success' => true,
            'data' => [
                'id' => 22345,
                'reference' => 'MTX-SANDBOX-PAYMENT-URL',
                'amount' => 25000,
                'currency' => 'XOF',
                'status' => 'pending',
                'gateway' => 'checkout',
                'payment_url' => 'https://checkout.geniuspay.ci/pay/payment-url-test',
                'environment' => 'sandbox',
                'metadata' => [
                    'proxiservices_payment_id' => '1',
                ],
            ],
        ], 201),
    ]);

    $this->actingAs($fixture['client'])
        ->post(route('client.requests.payments.store', [
            'serviceRequest' => $fixture['serviceRequest']->id,
        ]))
        ->assertRedirect(
            'https://checkout.geniuspay.ci/pay/payment-url-test',
        );

    $this->assertDatabaseHas('service_payments', [
        'service_request_id' => $fixture['serviceRequest']->id,
        'provider_reference' => 'MTX-SANDBOX-PAYMENT-URL',
        'status' => 'pending',
        'environment' => 'sandbox',
    ]);
});
