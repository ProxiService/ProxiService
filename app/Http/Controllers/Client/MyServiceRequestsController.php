<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ServicePayment;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest as ServiceRequestModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyServiceRequestsController extends Controller
{
    /**
     * Afficher uniquement les demandes du client connecté.
     */
    public function __invoke(Request $request): Response
    {
        $serviceRequests = ServiceRequestModel::query()
            ->where('client_id', $request->user()->id)
            ->with([
                'service:id,name,slug,pricing_type,base_price',
                'providerProfile:id,user_id,business_name,city,neighborhood',
                'providerProfile.user:id,name',
                'providerProfile.services:id',
                'review:id,service_request_id,rating,comment',
                'quotes:id,service_request_id,amount,description,status,responded_at,created_at',
                'payments:id,service_request_id,amount,status,checkout_url,environment,created_at,paid_at',
            ])
            ->latest()
            ->paginate(10)
            ->through(
                function (ServiceRequestModel $serviceRequest): array {
                    $service = $serviceRequest->service;

                    $providerService = $serviceRequest->providerProfile
                        ?->services
                        ->firstWhere('id', $service->id);

                    $acceptedQuote = $serviceRequest->quotes
                        ->firstWhere('status', 'accepted');

                    $providerPrice = $serviceRequest->providerProfile
                        ? $service->getPriceForProvider($serviceRequest->providerProfile->id)
                        : $service->base_price;
                    $paymentAmount = null;

                    if ($service->pricing_type === 'quote') {
                        $paymentAmount = $acceptedQuote
                        ? (float) $acceptedQuote->amount
                        : null;
                    } else {
                        $fixedPrice = $providerPrice ?? $service->base_price;

                        if (
                            is_numeric($fixedPrice)
                            && (float) $fixedPrice > 0
                        ) {
                            $paymentAmount = (float) $fixedPrice;
                        }
                    }

                    $paymentAvailable = $serviceRequest->status === 'accepted'
                        && $paymentAmount !== null
                        && $paymentAmount >= 200;

                    $paymentCompleted = $serviceRequest->payments
                        ->contains(
                            fn (ServicePayment $payment): bool => $payment->status === 'completed',
                        );

                    $latestPayment = $serviceRequest->payments->first();

                    return [
                        'id' => $serviceRequest->id,
                        'description' => $serviceRequest->description,
                        'city' => $serviceRequest->city,
                        'neighborhood' => $serviceRequest->neighborhood,
                        'address' => $serviceRequest->address,
                        'scheduled_at' => $serviceRequest->scheduled_at
                            ?->format('d/m/Y H:i'),
                        'is_urgent' => $serviceRequest->is_urgent,
                        'status' => $serviceRequest->status,
                        'created_at' => $serviceRequest->created_at
                            ?->format('d/m/Y H:i'),
                        'review' => $serviceRequest->review
                        ? [
                            'rating' => $serviceRequest->review->rating,
                            'comment' => $serviceRequest->review->comment,
                        ]
                        : null,

                        'service' => [
                            'name' => $service->name,
                            'slug' => $service->slug,
                            'pricing_type' => $service->pricing_type,
                            'base_price' => $service->base_price,
                        ],

                        'quotes' => $serviceRequest->quotes
                            ->map(fn (ServiceQuote $quote): array => [
                                'id' => $quote->id,
                                'amount' => $quote->amount,
                                'description' => $quote->description,
                                'status' => $quote->status,
                                'created_at' => $quote->created_at
                                    ?->format('d/m/Y H:i'),
                                'responded_at' => $quote->responded_at
                                    ?->format('d/m/Y H:i'),
                            ])
                            ->values(),

                        'provider' => [
                            'name' => $serviceRequest->providerProfile->business_name
                                ?: $serviceRequest->providerProfile->user->name,
                            'city' => $serviceRequest->providerProfile->city,
                            'neighborhood' => $serviceRequest->providerProfile->neighborhood,
                        ],

                        'payment_available' => $paymentAvailable,
                        'payment_amount' => $paymentAmount,
                        'payment_completed' => $paymentCompleted,
                        'payment' => $latestPayment
                        ? [
                            'id' => $latestPayment->id,
                            'status' => $latestPayment->status,
                            'amount' => (float) $latestPayment->amount,
                            'created_at' => $latestPayment->created_at
                                ?->format('d/m/Y H:i'),
                            'paid_at' => $latestPayment->paid_at
                                ?->format('d/m/Y H:i'),
                            'checkout_available' => (bool) $latestPayment->checkout_url
                                && $latestPayment->environment === config(
                                    'services.geniuspay.environment',
                                    'sandbox',
                                ),
                        ]
                        : null,
                    ];
                },
            );

        return Inertia::render('client/requests/index', [
            'requests' => $serviceRequests,
        ]);
    }
}
