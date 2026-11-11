<?php

namespace App\Http\Controllers\Client;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceCommission;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Notifications\NewServiceRequestNotification;
use App\Notifications\ServiceRequestStatusNotification;
use App\Services\CommissionCalculator;
use App\Services\PrePaymentContactGuard;
use App\Services\UrgentRequestDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    public function urgentCreate(): Response
    {
        return Inertia::render('client/requests/urgent-create', [
            'services' => Service::query()->with('category:id,name')
                ->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')->get(['id', 'category_id', 'name'])
                ->map(fn (Service $service): array => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category' => $service->category?->name,
                ]),
        ]);
    }

    public function storeUrgent(Request $request, UrgentRequestDispatcher $dispatcher): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'city' => ['required', 'string', 'max:100'],
            'neighborhood' => ['required', 'string', 'max:100'],
            'location_consent' => ['required', 'accepted'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        app(PrePaymentContactGuard::class)->ensureNoContactDetails($validated['description'], 'description');
        $service = Service::query()->whereKey($validated['service_id'])->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))->firstOrFail();

        $serviceRequest = new ServiceRequestModel;
        $serviceRequest->client()->associate($request->user());
        $serviceRequest->service()->associate($service);
        $serviceRequest->fill([
            'description' => $validated['description'],
            'city' => $validated['city'],
            'neighborhood' => $validated['neighborhood'],
            'is_urgent' => true,
        ]);
        $serviceRequest->provider_profile_id = null;
        $serviceRequest->urgent_latitude = (float) $validated['latitude'];
        $serviceRequest->urgent_longitude = (float) $validated['longitude'];
        $serviceRequest->urgent_expires_at = now()->addMinutes(UrgentRequestDispatcher::EXPIRE_AFTER_MINUTES);
        $serviceRequest->urgent_dispatch_status = 'searching';
        $serviceRequest->status = 'pending';
        $serviceRequest->save();
        $dispatcher->dispatch($serviceRequest->load('service'), UrgentRequestDispatcher::INITIAL_RADIUS_KM);

        return to_route('client.requests.index')->with('success',
            'Votre demande urgente est diffusée aux prestataires proches. Votre position précise reste privée.');
    }

    /**
     * Vérifier que le service peut être demandé
     * auprès du prestataire sélectionné.
     */
    private function assertRequestable(
        Service $service,
        ProviderProfile $providerProfile
    ): void {
        $service->loadMissing('category');
        $providerProfile->loadMissing('user');

        abort_unless(
            $service->is_active && $service->category?->is_active,
            404
        );

        abort_unless(
            $providerProfile->status === 'approved'
                && $providerProfile->user?->role === 'prestataire',
            404
        );

        $offersService = $providerProfile->services()
            ->where('services.id', $service->id)
            ->wherePivot('is_active', true)
            ->exists();

        abort_unless($offersService, 404);
    }

    /**
     * Afficher le formulaire de demande.
     */
    public function create(
        Service $service,
        ProviderProfile $providerProfile
    ): Response {
        $this->assertRequestable($service, $providerProfile);

        $offeredService = $providerProfile->services()
            ->where('services.id', $service->id)
            ->wherePivot('is_active', true)
            ->firstOrFail();

        return Inertia::render('client/requests/create', [
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'description' => $service->description,
                'pricing_type' => $service->pricing_type,
                'base_price' => $service->base_price,
                'category' => [
                    'name' => $service->category->name,
                    'slug' => $service->category->slug,
                ],
            ],

            'provider' => [
                'id' => $providerProfile->id,
                'name' => $providerProfile->user->name,
                'business_name' => $providerProfile->business_name,
                'city' => $providerProfile->city,
                'neighborhood' => $providerProfile->neighborhood,
                'is_available' => $providerProfile->is_available,
                'price' => $offeredService->pivot->price,
            ],
        ]);
    }

    /**
     * Enregistrer une nouvelle demande.
     */
    public function store(
        Request $request,
        Service $service,
        ProviderProfile $providerProfile
    ): RedirectResponse {
        $this->assertRequestable($service, $providerProfile);

        $validated = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'city' => ['required', 'string', 'max:100'],
            'neighborhood' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => [
                'nullable',
                'date',
                'after_or_equal:now',
            ],
            'is_urgent' => ['sometimes', 'boolean'],
        ]);

        app(PrePaymentContactGuard::class)->ensureNoContactDetails($validated['description'], 'description');

        $serviceRequest = new ServiceRequestModel;

        // Ces relations sont définies côté serveur.
        // Le formulaire ne choisit ni le client, ni le prestataire,
        // ni le service, ni le statut.
        $serviceRequest->client()->associate($request->user());
        $serviceRequest->providerProfile()->associate($providerProfile);
        $serviceRequest->service()->associate($service);

        $serviceRequest->fill($validated);
        $serviceRequest->is_urgent = $validated['is_urgent'] ?? false;
        $serviceRequest->status = 'pending';

        $serviceRequest->save();

        $providerProfile->user->notify(
            new NewServiceRequestNotification(
                serviceRequestId: $serviceRequest->id,
                serviceName: $service->name,
                clientName: $request->user()->name,
            ),
        );

        return to_route('client.dashboard')
            ->with('success', 'Votre demande a été envoyée au prestataire.');
    }

    /**
     * Confirmer la fin de la prestation et enregistrer sa commission.
     */
    public function confirmCompletion(
        Request $request,
        ServiceRequestModel $serviceRequest,
        CommissionCalculator $commissionCalculator,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $serviceRequest, $commissionCalculator): void {
            $lockedRequest = ServiceRequestModel::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $lockedRequest->client_id === (int) $request->user()->id,
                404,
                'Demande introuvable.',
            );

            abort_unless(
                $lockedRequest->status === 'awaiting_confirmation',
                409,
                'Cette demande ne peut pas être confirmée dans son état actuel.',
            );
            abort_if($lockedRequest->hasOpenDispute(), 409,
                'Une prestation signalée ne peut pas être confirmée avant l’arbitrage.');

            $completedPayment = $lockedRequest->payments()
                ->where('status', 'completed')
                ->latest()
                ->lockForUpdate()
                ->first();

            abort_unless(
                $completedPayment !== null,
                409,
                'Un paiement confirmé est nécessaire pour terminer cette prestation.',
            );

            abort_if(
                $lockedRequest->commission()->exists(),
                409,
                'La commission de cette prestation a déjà été enregistrée.',
            );

            $lockedRequest->load([
                'service',
                'providerProfile',
            ]);

            $service = $lockedRequest->service;

            if ($service->pricing_type === 'quote') {
                $acceptedQuote = $lockedRequest->quotes()
                    ->where('status', 'accepted')
                    ->first();

                abort_unless(
                    $acceptedQuote !== null,
                    409,
                    'Aucun devis accepté ne permet de calculer la commission.',
                );

                $grossAmount = (float) $acceptedQuote->amount;
            } else {
                $providerService = $lockedRequest->providerProfile
                    ->services()
                    ->where('services.id', $service->id)
                    ->first();

                $grossAmount = $providerService?->pivot?->price;

                if ($grossAmount === null) {
                    $grossAmount = $service->base_price;
                }

                abort_unless(
                    is_numeric($grossAmount) && (float) $grossAmount > 0,
                    409,
                    'Aucun prix valide n’est disponible pour calculer la commission.',
                );

                $grossAmount = (float) $grossAmount;
            }

            abort_unless(
                abs((float) $completedPayment->amount - (float) $grossAmount) <= 0.01,
                409,
                'Le montant payé ne correspond pas au prix convenu pour cette prestation.',
            );

            $breakdown = $commissionCalculator->breakdown($grossAmount);

            $commission = new ServiceCommission([
                'gross_amount' => $grossAmount,
                'commission_rate' => $breakdown['rate'],
                'commission_amount' => $breakdown['commission'],
                'provider_amount' => $breakdown['net'],
                'collection_status' => 'pending',
                'calculated_at' => now(),
            ]);

            $lockedRequest->commission()->save($commission);

            abort_unless($lockedRequest->transitionTo(ServiceRequestStatus::Completed), 409,
                'Cette demande ne peut pas passer à l’état terminé.');
            $lockedRequest->save();
        });

        $updatedRequest = $serviceRequest->fresh([
            'service:id,name',
            'providerProfile.user:id,name',
        ]);

        $providerUser = $updatedRequest?->providerProfile?->user;

        if ($updatedRequest && $providerUser) {
            $providerUser->notify(
                new ServiceRequestStatusNotification(
                    serviceRequestId: (int) $updatedRequest->id,
                    serviceName: $updatedRequest->service->name,
                    status: 'completed',
                    actorName: $request->user()->name,
                    recipientRole: 'prestataire',
                ),
            );
        }

        return redirect()
            ->route('client.requests.index')
            ->with(
                'success',
                'La prestation est terminée et la commission prévisionnelle a été enregistrée.',
            );
    }
}
