<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ServicePayment;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Services\GeniusPayCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ServicePaymentController extends Controller
{
    /**
     * Prepare and open a GeniusPay checkout.
     */
    public function store(
        Request $request,
        ServiceRequestModel $serviceRequest,
        GeniusPayCheckoutService $geniusPay,
    ): Response {
        abort_unless(
            (int) $serviceRequest->client_id === (int) $request->user()->id,
            404,
            'Demande introuvable.',
        );

        abort_unless(
            $serviceRequest->status === 'accepted',
            409,
            'Le paiement est possible uniquement pour une demande acceptée.',
        );

        $serviceRequest->load([
            'service',
            'providerProfile',
        ]);

        $amount = $this->resolveAmount($serviceRequest);

        abort_unless(
            $amount >= 200,
            409,
            'Le montant de cette prestation est inférieur au minimum accepté par GeniusPay.',
        );

        if (
            $serviceRequest->payments()
                ->where('status', 'completed')
                ->exists()
        ) {
            abort(409, 'Cette prestation possède déjà un paiement confirmé.');
        }

        $activePayment = $serviceRequest->payments()
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        if ($activePayment !== null) {
            if (
                $activePayment->checkout_url
                && $activePayment->environment === config(
                    'services.geniuspay.environment',
                    'sandbox',
                )
            ) {
                return Inertia::location($activePayment->checkout_url);
            }

            abort(
                409,
                'Une tentative de paiement existe déjà. Attends son résultat avant de réessayer.',
            );
        }

        $payment = new ServicePayment([
            'provider' => 'geniuspay',
            'amount' => $amount,
            'currency' => 'XOF',
            'status' => 'pending',
            'environment' => config(
                'services.geniuspay.environment',
                'sandbox',
            ),
        ]);

        $payment->client()->associate($request->user());
        $serviceRequest->payments()->save($payment);

        try {
            $checkout = $geniusPay->createCheckout(
                $payment,
                $serviceRequest,
                $request->user(),
            );

            $payment->forceFill($checkout)->save();
        } catch (Throwable $exception) {
            Log::error('Impossible de créer le checkout GeniusPay.', [
                'service_payment_id' => $payment->id,
                'exception_class' => $exception::class,
            ]);

            $payment->forceFill([
                'status' => 'failed',
                'failure_reason' => 'Impossible d’initialiser le checkout GeniusPay.',
            ])->save();

            return back()->withErrors([
                'payment' => 'GeniusPay n’a pas pu préparer le paiement. Vérifie ta configuration et réessaie.',
            ]);
        }

        return Inertia::location($payment->checkout_url);
    }

    /**
     * The browser return does not confirm payment.
     */
    public function returned(): Response
    {
        return redirect()
            ->route('client.requests.index')
            ->with(
                'info',
                'Retour de GeniusPay reçu. Le statut du paiement sera confirmé séparément par le serveur.',
            );
    }

    /**
     * Resolve the agreed service amount.
     */
    private function resolveAmount(
        ServiceRequestModel $serviceRequest,
    ): float {
        $service = $serviceRequest->service;

        if ($service->pricing_type === 'quote') {
            $acceptedQuote = $serviceRequest->quotes()
                ->where('status', 'accepted')
                ->latest()
                ->first();

            abort_unless(
                $acceptedQuote !== null,
                409,
                'Le client doit accepter un devis avant de payer.',
            );

            return round((float) $acceptedQuote->amount, 2);
        }

        $amount = $serviceRequest->providerProfile
            ? $service->getPriceForProvider($serviceRequest->providerProfile->id)
            : $service->base_price;

        abort_unless(
            is_numeric($amount) && (float) $amount > 0,
            409,
            'Aucun prix valide n’est défini pour cette prestation.',
        );

        return round((float) $amount, 2);
    }
}
