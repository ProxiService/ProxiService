<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceQuoteController extends Controller
{
    /**
     * Proposer un devis pour une demande de type quote.
     */
    public function store(
        Request $request,
        ServiceRequest $serviceRequest,
    ): RedirectResponse {
        $this->ensureOwnRequest($request, $serviceRequest);

        abort_unless(
            $serviceRequest->service?->pricing_type === 'quote',
            409,
            'Cette demande n’accepte pas de devis.',
        );

        abort_unless(
            $serviceRequest->status === 'accepted',
            409,
            'La demande doit être acceptée avant de proposer un devis.',
        );

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $hasActiveQuote = $serviceRequest->quotes()
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        abort_unless(
            ! $hasActiveQuote,
            409,
            'Un devis est déjà actif pour cette demande.',
        );

        ServiceQuote::create([
            'service_request_id' => $serviceRequest->id,
            'provider_profile_id' => $request->user()->providerProfile?->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'status' => 'pending',
        ]);

        return redirect()
            ->route('prestataire.requests.index')
            ->with('success', 'Le devis a été proposé au client.');
    }

    /**
     * Vérifier que la demande appartient au prestataire connecté.
     */
    private function ensureOwnRequest(
        Request $request,
        ServiceRequest $serviceRequest,
    ): void {
        $providerProfile = $request->user()->providerProfile;

        abort_unless(
            $providerProfile
            && (int) $serviceRequest->provider_profile_id === (int) $providerProfile->id,
            404,
            'Demande introuvable.',
        );
    }
}
