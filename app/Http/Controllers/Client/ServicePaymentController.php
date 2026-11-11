<?php

namespace App\Http\Controllers\Client;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\ServicePayment;
use App\Models\ServiceRequest as ServiceRequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        PaymentGateway $paymentGateway,
    ): Response {
        abort_unless(Gate::allows('manageAsClient', $serviceRequest), 404, 'Demande introuvable.');
        abort_if($serviceRequest->is_urgent, 409, 'Le paiement des demandes urgentes reste désactivé tant que le fournisseur financier n’est pas validé.');

        $result = DB::transaction(function () use ($serviceRequest, $request): array {
            $lockedRequest = ServiceRequestModel::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $lockedRequest->client_id === (int) $request->user()->id,
                404,
                'Demande introuvable.',
            );
            abort_if($lockedRequest->is_urgent, 409, 'Le paiement des demandes urgentes reste désactivé.');
            abort_unless(
                $lockedRequest->status === 'accepted',
                409,
                'Le paiement est possible uniquement pour une demande acceptée.',
            );
            abort_if($lockedRequest->hasOpenDispute(), 409,
                'Le paiement est bloqué pendant le traitement du signalement.');
            $lockedRequest->load(['service', 'providerProfile']);
            $amount = $this->resolveAmount($lockedRequest);
            abort_unless($amount >= 200, 409,
                'Le montant de cette prestation est inférieur au minimum accepté par GeniusPay.');

            abort_unless(
                ! $lockedRequest->payments()->where('status', 'completed')->exists(),
                409,
                'Cette prestation possède déjà un paiement confirmé.',
            );

            $activePayment = $lockedRequest->payments()
                ->whereIn('status', ['pending', 'processing'])
                ->latest()
                ->first();
            if ($activePayment !== null) {
                if ($activePayment->checkout_url && $activePayment->environment === config(
                    'services.geniuspay.environment', 'sandbox',
                )) {
                    return ['payment' => $activePayment, 'reused' => true];
                }

                abort(409, 'Une tentative de paiement existe déjà. Attends son résultat avant de réessayer.');
            }

            $payment = new ServicePayment([
                'provider' => 'geniuspay',
                'amount' => $amount,
                'currency' => 'XOF',
                'status' => 'pending',
                'environment' => config('services.geniuspay.environment', 'sandbox'),
            ]);
            $payment->client()->associate($request->user());
            $lockedRequest->payments()->save($payment);

            return ['payment' => $payment, 'reused' => false];
        });

        /** @var ServicePayment $payment */
        $payment = $result['payment'];

        if ($result['reused']) {
            return Inertia::location($payment->checkout_url);
        }

        $serviceRequest->load(['service', 'providerProfile']);

        try {
            $checkout = $paymentGateway->createCheckout(
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
