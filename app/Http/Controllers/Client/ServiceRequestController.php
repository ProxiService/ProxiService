<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceCommission;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Notifications\NewServiceRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
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
    ): RedirectResponse {
        DB::transaction(function () use ($request, $serviceRequest): void {
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

            $commissionRate = (float) config(
                'proxiservices.commission_rate',
                10.0,
            );

            abort_if(
                ! is_finite($commissionRate)
                    || $commissionRate < 0
                    || $commissionRate > 100,
                500,
                'Le taux de commission configuré est invalide.',
            );

            $commissionAmount = round(
                $grossAmount * $commissionRate / 100,
                2,
            );

            $providerAmount = round(
                $grossAmount - $commissionAmount,
                2,
            );

            $commission = new ServiceCommission([
                'gross_amount' => $grossAmount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'provider_amount' => $providerAmount,
                'collection_status' => 'pending',
                'calculated_at' => now(),
            ]);

            $lockedRequest->commission()->save($commission);

            $lockedRequest->status = 'completed';
            $lockedRequest->save();
        });

        return redirect()
            ->route('client.requests.index')
            ->with(
                'success',
                'La prestation est terminée et la commission prévisionnelle a été enregistrée.',
            );
    }
}
