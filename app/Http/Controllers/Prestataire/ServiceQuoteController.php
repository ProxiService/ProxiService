<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $serviceRequest, $validated): void {
            $lockedRequest = ServiceRequest::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedRequest->provider_profile_id === (int) $request->user()->providerProfile?->id, 404,
                'Demande introuvable.');
            abort_unless($lockedRequest->service?->pricing_type === 'quote', 409,
                'Cette demande n’accepte pas de devis.');
            abort_unless($lockedRequest->status === 'accepted', 409,
                'La demande doit être acceptée avant de proposer un devis.');
            abort_if($lockedRequest->hasOpenDispute(), 409,
                'La demande est gelée pendant le traitement du signalement.');

            abort_if($lockedRequest->quotes()->whereIn('status', ['pending', 'accepted'])->exists(), 409,
                'Un devis est déjà actif pour cette demande.');

            ServiceQuote::create([
                'service_request_id' => $lockedRequest->id,
                'provider_profile_id' => $request->user()->providerProfile?->id,
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'status' => 'pending',
            ]);
        });

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
