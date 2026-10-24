<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Notifications\NewServiceRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Confirmer que la prestation est terminée.
     */
    public function confirmCompletion(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        abort_unless(
            (int) $serviceRequest->client_id === (int) $request->user()->id,
            404,
            'Demande introuvable.',
        );

        abort_unless(
            $serviceRequest->status === 'awaiting_confirmation',
            409,
            'Cette demande ne peut pas être confirmée dans son état actuel.',
        );

        $serviceRequest->status = 'completed';
        $serviceRequest->save();

        return redirect()
            ->route('client.requests.index')
            ->with(
                'success',
                'La fin de la prestation a été confirmée.',
            );
    }
}
