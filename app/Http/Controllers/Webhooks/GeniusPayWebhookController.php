<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ServiceCommission;
use App\Models\ServicePayment;
use App\Notifications\ServicePaymentStatusNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;

class GeniusPayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.geniuspay.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('Le secret webhook GeniusPay n’est pas configuré.');

            return response()->json([
                'success' => false,
                'message' => 'Webhook non configuré.',
            ], 503);
        }

        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');

        if (
            ! is_string($signature)
            || preg_match('/\A[0-9a-f]{64}\z/i', $signature) !== 1
            || ! is_string($timestamp)
            || ! ctype_digit($timestamp)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'En-têtes de signature invalides.',
            ], 400);
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > 300) {
            return response()->json([
                'success' => false,
                'message' => 'Timestamp expiré.',
            ], 400);
        }

        $rawPayload = $request->getContent();
        $expectedSignature = hash_hmac(
            'sha256',
            $timestamp.'.'.$rawPayload,
            $secret,
        );

        if (! hash_equals($expectedSignature, strtolower($signature))) {
            Log::warning('Signature webhook GeniusPay invalide.');

            return response()->json([
                'success' => false,
                'message' => 'Signature invalide.',
            ], 401);
        }

        try {
            $payload = json_decode(
                $rawPayload,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return response()->json([
                'success' => false,
                'message' => 'Payload invalide.',
            ], 400);
        }

        if (! is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Payload invalide.',
            ], 400);
        }

        $headerEvent = $request->header('X-Webhook-Event');
        $payloadEvent = $payload['event'] ?? null;

        if (
            ! is_string($headerEvent)
            || ! is_string($payloadEvent)
            || $headerEvent !== $payloadEvent
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Événement incohérent.',
            ], 400);
        }

        $expectedEnvironment = (string) config(
            'services.geniuspay.environment',
            'sandbox',
        );
        $headerEnvironment = $request->header('X-Webhook-Environment');
        $payloadEnvironment = $payload['environment'] ?? null;

        if (
            ! is_string($headerEnvironment)
            || ! is_string($payloadEnvironment)
            || $headerEnvironment !== $payloadEnvironment
            || $headerEnvironment !== $expectedEnvironment
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Environnement de paiement incohérent.',
            ], 400);
        }

        $eventStatuses = [
            'payment.initiated' => 'processing',
            'payment.success' => 'completed',
            'payment.failed' => 'failed',
            'payment.cancelled' => 'cancelled',
            'payment.expired' => 'expired',
            'payment.refunded' => 'refunded',
        ];

        if (! isset($eventStatuses[$payloadEvent])) {
            return response()->json([
                'success' => true,
                'result' => 'ignored',
            ]);
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Données de transaction absentes.',
            ], 400);
        }

        $reference = $data['reference'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        $gatewayStatus = $data['status'] ?? null;

        if (
            ! is_string($reference)
            || $reference === ''
            || strlen($reference) > 100
            || ! is_numeric($amount)
            || ! is_finite((float) $amount)
            || ! is_string($currency)
            || ! is_string($gatewayStatus)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Données de transaction invalides.',
            ], 400);
        }

        $expectedGatewayStatuses = [
            'payment.initiated' => ['pending', 'processing'],
            'payment.success' => ['completed'],
            'payment.failed' => ['failed'],
            'payment.cancelled' => ['cancelled', 'canceled'],
            'payment.expired' => ['expired'],
            'payment.refunded' => ['refunded'],
        ];

        if (! in_array(
            $gatewayStatus,
            $expectedGatewayStatuses[$payloadEvent],
            true,
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Statut de transaction incohérent.',
            ], 422);
        }

        $transactionId = $data['id'] ?? null;
        $fees = $data['fees'] ?? null;
        $netAmount = $data['net_amount'] ?? null;
        $method = $data['payment_method']
            ?? $data['provider']
            ?? null;

        if (
            ($transactionId !== null
                && (! is_scalar($transactionId) || strlen((string) $transactionId) > 100))
            || ($fees !== null
                && (! is_numeric($fees) || ! is_finite((float) $fees) || (float) $fees < 0))
            || ($netAmount !== null
                && (! is_numeric($netAmount) || ! is_finite((float) $netAmount) || (float) $netAmount < 0))
            || ($method !== null
                && (! is_string($method) || strlen($method) > 40))
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Données de transaction invalides.',
            ], 400);
        }

        $targetStatus = $eventStatuses[$payloadEvent];

        $result = DB::transaction(function () use (
            $reference,
            $headerEnvironment,
            $currency,
            $amount,
            $targetStatus,
            $data,
            $transactionId,
            $fees,
            $netAmount,
            $method,
        ): string {
            $payment = ServicePayment::query()
                ->where('provider', 'geniuspay')
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return 'not_found';
            }

            if (
                $payment->environment !== $headerEnvironment
                || $payment->currency !== $currency
                || number_format((float) $payment->amount, 2, '.', '')
                    !== number_format((float) $amount, 2, '.', '')
            ) {
                return 'mismatch';
            }

            $metadata = $data['metadata'] ?? null;
            $metadataPaymentId = is_array($metadata)
                ? ($metadata['proxiservices_payment_id'] ?? null)
                : null;

            if (
                $metadataPaymentId !== null
                && (string) $metadataPaymentId !== (string) $payment->id
            ) {
                return 'metadata_mismatch';
            }

            $currentStatus = $payment->status;

            if ($currentStatus === $targetStatus) {
                return 'duplicate';
            }

            if ($currentStatus === 'refunded') {
                return 'ignored';
            }

            if (
                $currentStatus === 'completed'
                && in_array(
                    $targetStatus,
                    ['processing', 'failed', 'cancelled', 'expired'],
                    true,
                )
            ) {
                return 'ignored';
            }

            if (
                $targetStatus === 'refunded'
                && $currentStatus !== 'completed'
            ) {
                return 'ignored';
            }

            if (
                in_array(
                    $currentStatus,
                    ['failed', 'cancelled', 'expired'],
                    true,
                )
                && in_array(
                    $targetStatus,
                    ['processing', 'failed', 'cancelled', 'expired'],
                    true,
                )
            ) {
                return 'ignored';
            }

            $payment->status = $targetStatus;

            if ($targetStatus === 'completed') {
                $payment->paid_at = now();
                $payment->failure_reason = null;
            }

            if ($transactionId !== null) {
                $payment->provider_transaction_id = (string) $transactionId;
            }

            if ($fees !== null) {
                $payment->fees = (float) $fees;
            }

            if ($netAmount !== null) {
                $payment->net_amount = (float) $netAmount;
            }

            if ($method !== null) {
                $payment->payment_method = $method;
            }

            if (in_array(
                $targetStatus,
                ['failed', 'cancelled', 'expired'],
                true,
            )) {
                $payment->failure_reason =
                    'Statut communiqué par GeniusPay : '.$targetStatus;
            }

            $payment->save();

            if ($targetStatus === 'refunded') {
                $commission = ServiceCommission::query()
                    ->where('service_request_id', $payment->service_request_id)
                    ->lockForUpdate()
                    ->first();

                if ($commission !== null && ! $commission->is_reversed) {
                    $commission->is_reversed = true;
                    $commission->reversed_at = now();
                    $commission->reversal_reason =
                        'Paiement remboursé par GeniusPay.';
                    $commission->save();
                }
            }

            // Verify that the associated service request is still in a valid state for payment completion
            if ($targetStatus === 'completed') {
                $serviceRequest = $payment->serviceRequest;

                if (! $serviceRequest || $serviceRequest->status !== 'accepted') {
                    // If the request is no longer 'accepted' (e.g. cancelled), we should not mark the payment as completed
                    // as it might fulfill an invalid request.
                    Log::warning('Paiement complété pour une demande qui n\'est plus acceptée.', [
                        'payment_id' => $payment->id,
                        'service_request_id' => $serviceRequest?->id,
                        'status' => $serviceRequest?->status,
                    ]);

                    // We might want to trigger a refund or mark the payment as 'manual_review'
                    $payment->status = 'manual_review';
                    $payment->save();

                    return 'manual_review';
                }
            }

            return 'updated';
        });

        if ($result === 'not_found') {
            Log::warning('Référence GeniusPay inconnue.', [
                'reference' => $reference,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Paiement introuvable.',
            ], 404);
        }

        if (in_array($result, ['mismatch', 'metadata_mismatch'], true)) {
            Log::warning('Incohérence sur un webhook GeniusPay.', [
                'reference' => $reference,
                'result' => $result,
            ]);

            return response()->json([
                'success' => false,
                'message' => $result === 'metadata_mismatch'
                    ? 'Métadonnées de paiement incohérentes.'
                    : 'Les données du paiement ne correspondent pas.',
            ], 422);
        }

        if (
            $result === 'updated'
            && in_array(
                $targetStatus,
                ['completed', 'failed', 'cancelled', 'expired', 'refunded'],
                true,
            )
        ) {
            $payment = ServicePayment::query()
                ->with([
                    'client',
                    'serviceRequest.service',
                    'serviceRequest.providerProfile.user',
                ])
                ->where('provider', 'geniuspay')
                ->where('provider_reference', $reference)
                ->firstOrFail();

            $serviceRequest = $payment->serviceRequest;
            $client = $payment->client;

            if ($serviceRequest !== null && $client !== null) {
                $notificationData = [
                    'paymentId' => (int) $payment->id,
                    'serviceRequestId' => (int) $serviceRequest->id,
                    'serviceName' => $serviceRequest->service->name,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'paymentStatus' => $payment->status,
                ];

                $client->notify(
                    new ServicePaymentStatusNotification(
                        ...$notificationData,
                        recipientRole: 'client',
                    ),
                );

                if (in_array($payment->status, ['completed', 'refunded'], true)) {
                    $providerUser = $serviceRequest->providerProfile?->user;

                    if ($providerUser !== null) {
                        $providerUser->notify(
                            new ServicePaymentStatusNotification(
                                ...$notificationData,
                                recipientRole: 'prestataire',
                            ),
                        );
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }
}
