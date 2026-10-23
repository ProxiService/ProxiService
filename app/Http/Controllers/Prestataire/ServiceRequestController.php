<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest as ServiceRequestModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    /**
     * Afficher les demandes reçues par le prestataire connecté.
     */
    public function index(Request $request): Response
    {
        $providerProfileId = $request->user()->providerProfile()->value('id');

        $requests = ServiceRequestModel::query()
            ->where('provider_profile_id', $providerProfileId ?? 0)
            ->with([
                'client:id,name',
                'service:id,name,slug',
            ])
            ->latest()
            ->paginate(10)
            ->through(
                fn (ServiceRequestModel $serviceRequest): array => [
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
                    'client' => [
                        'name' => $serviceRequest->client->name,
                    ],
                    'service' => [
                        'name' => $serviceRequest->service->name,
                        'slug' => $serviceRequest->service->slug,
                    ],
                ],
            );

        return Inertia::render('prestataire/requests/index', [
            'requests' => $requests,
        ]);
    }

    /**
     * Accepter une demande en attente.
     */
    public function accept(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $this->ensureOwnRequest($request, $serviceRequest);

        abort_unless(
            $serviceRequest->status === 'pending',
            409,
            'Cette demande a déjà été traitée.',
        );

        $serviceRequest->status = 'accepted';
        $serviceRequest->save();

        return redirect()
            ->route('prestataire.requests.index')
            ->with('success', 'La demande a été acceptée.');
    }

    /**
     * Refuser une demande en attente.
     */
    public function reject(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $this->ensureOwnRequest($request, $serviceRequest);

        abort_unless(
            $serviceRequest->status === 'pending',
            409,
            'Cette demande a déjà été traitée.',
        );

        $serviceRequest->status = 'rejected';
        $serviceRequest->save();

        return redirect()
            ->route('prestataire.requests.index')
            ->with('success', 'La demande a été refusée.');
    }

    /**
     * Vérifier que la demande appartient au prestataire connecté.
     */
    private function ensureOwnRequest(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): void {
        $providerProfile = $request->user()->providerProfile;

        abort_unless(
            $providerProfile
            && (int) $serviceRequest->provider_profile_id === (int) $providerProfile->id,
            404,
            'Demande introuvable.',
        );
    }

    /**
     * Indiquer que l'intervention a commencé.
     */
    public function start(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $this->ensureOwnRequest($request, $serviceRequest);

        abort_unless(
            $serviceRequest->status === 'accepted',
            409,
            'Cette demande ne peut pas être démarrée dans son état actuel.',
        );

        $serviceRequest->status = 'in_progress';
        $serviceRequest->save();

        return redirect()
            ->route('prestataire.requests.index')
            ->with('success', 'L’intervention a commencé.');
    }

    /**
     * Signaler que l'intervention est terminée.
     */
    public function finish(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $this->ensureOwnRequest($request, $serviceRequest);

        abort_unless(
            $serviceRequest->status === 'in_progress',
            409,
            'Seule une intervention en cours peut être terminée.',
        );

        $serviceRequest->status = 'awaiting_confirmation';
        $serviceRequest->save();

        return redirect()
            ->route('prestataire.requests.index')
            ->with(
                'success',
                'La fin de l’intervention a été signalée au client.',
            );
    }
}
