<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\ServicePayment;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeniusPayCheckoutService implements PaymentGateway
{
    /**
     * Create a hosted checkout with GeniusPay without confirming payment.
     *
     * @return array{
     *     provider_reference: string,
     *     provider_transaction_id: string|null,
     *     checkout_url: string,
     *     fees: float|null,
     *     net_amount: float|null,
     *     payment_method: string|null,
     *     metadata: array<array-key, mixed>,
     *     environment: string
     * }
     */
    public function createCheckout(
        ServicePayment $payment,
        ServiceRequestModel $serviceRequest,
        User $customer,
    ): array {
        $apiKey = config('services.geniuspay.api_key');
        $apiSecret = config('services.geniuspay.api_secret');

        if (
            ! is_string($apiKey)
            || $apiKey === ''
            || ! is_string($apiSecret)
            || $apiSecret === ''
        ) {
            throw new RuntimeException(
                'Les clés API GeniusPay ne sont pas configurées.',
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'services.geniuspay.base_url',
                'https://geniuspay.ci/api/v1/merchant',
            ),
            '/',
        );

        $metadata = [
            'proxiservices_payment_id' => (string) $payment->id,
            'service_request_id' => (string) $serviceRequest->id,
        ];

        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'X-API-Secret' => $apiSecret,
            ])
            ->post("{$baseUrl}/payments", [
                'amount' => (float) $payment->amount,
                'currency' => 'XOF',
                'description' => sprintf(
                    'ProxiServices - demande #%d - %s',
                    $serviceRequest->id,
                    $serviceRequest->service->name,
                ),
                'country' => 'CI',
                'customer' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'country' => 'CI',
                ],
                'success_url' => route('client.payments.return'),
                'error_url' => route('client.payments.return'),
                'metadata' => $metadata,
            ]);

        $response->throw();

        $responseData = $response->json();

        if (
            ! is_array($responseData)
            || (
                array_key_exists('success', $responseData)
                && $responseData['success'] !== true
            )
        ) {
            throw new RuntimeException(
                'GeniusPay a renvoyé une réponse de paiement invalide.',
            );
        }

        $data = $responseData['data'] ?? null;

        if (! is_array($data)) {
            throw new RuntimeException(
                'GeniusPay a renvoyé une réponse de paiement invalide.',
            );
        }

        $reference = $data['reference'] ?? null;
        $checkoutUrl = $data['checkout_url']
            ?? $data['payment_url']
            ?? null;

        if (
            ! is_string($reference)
            || $reference === ''
            || strlen($reference) > 100
            || ! is_string($checkoutUrl)
            || $checkoutUrl === ''
        ) {
            throw new RuntimeException(
                'La référence ou l’URL du checkout est absente de la réponse GeniusPay.',
            );
        }

        $urlParts = parse_url($checkoutUrl);

        if (! is_array($urlParts)) {
            throw new RuntimeException(
                'GeniusPay a renvoyé une URL de checkout non autorisée.',
            );
        }

        $host = strtolower((string) ($urlParts['host'] ?? ''));
        $isGeniusPayHost = $host === 'geniuspay.ci'
            || str_ends_with($host, '.geniuspay.ci');

        if (
            ($urlParts['scheme'] ?? null) !== 'https'
            || ! $isGeniusPayHost
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
        ) {
            throw new RuntimeException(
                'GeniusPay a renvoyé une URL de checkout non autorisée.',
            );
        }

        if (
            isset($data['amount'])
            && (
                ! is_numeric($data['amount'])
                || abs((float) $data['amount'] - (float) $payment->amount) > 0.01
            )
        ) {
            throw new RuntimeException(
                'Le montant renvoyé par GeniusPay ne correspond pas au montant demandé.',
            );
        }

        if (
            isset($data['currency'])
            && $data['currency'] !== 'XOF'
        ) {
            throw new RuntimeException(
                'La devise renvoyée par GeniusPay ne correspond pas à la devise demandée.',
            );
        }

        $fees = $data['fees'] ?? null;
        $netAmount = $data['net_amount'] ?? null;

        if (
            ($fees !== null && (! is_numeric($fees) || (float) $fees < 0))
            || (
                $netAmount !== null
                && (! is_numeric($netAmount) || (float) $netAmount < 0)
            )
        ) {
            throw new RuntimeException(
                'Les frais ou le montant net renvoyés par GeniusPay sont invalides.',
            );
        }

        $environment = (string) (
            $data['environment']
            ?? config('services.geniuspay.environment', 'sandbox')
        );

        if (
            $environment !== config(
                'services.geniuspay.environment',
                'sandbox',
            )
        ) {
            throw new RuntimeException(
                'L’environnement de paiement renvoyé par GeniusPay ne correspond pas à la configuration.',
            );
        }

        $transactionId = isset($data['id'])
            ? (string) $data['id']
            : null;

        if ($transactionId !== null && strlen($transactionId) > 100) {
            throw new RuntimeException(
                'L’identifiant de transaction renvoyé par GeniusPay est invalide.',
            );
        }

        $paymentMethod = $data['gateway']
            ?? $data['payment_method']
            ?? null;

        if (
            $paymentMethod !== null
            && (! is_string($paymentMethod) || strlen($paymentMethod) > 40)
        ) {
            throw new RuntimeException(
                'Le moyen de paiement renvoyé par GeniusPay est invalide.',
            );
        }

        return [
            'provider_reference' => $reference,
            'provider_transaction_id' => $transactionId,
            'checkout_url' => $checkoutUrl,
            'fees' => $fees !== null
                ? (float) $fees
                : null,
            'net_amount' => $netAmount !== null
                ? (float) $netAmount
                : null,
            'payment_method' => $paymentMethod,
            'metadata' => is_array($data['metadata'] ?? null)
                ? $data['metadata']
                : $metadata,
            'environment' => $environment,
        ];
    }
}
