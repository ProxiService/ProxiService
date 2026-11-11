<?php

namespace App\Http\Controllers\Client;

use App\Enums\ServiceQuoteStatus;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ServiceQuoteController extends Controller
{
    /**
     * Accepter un devis proposé pour une demande de type quote.
     */
    public function accept(
        ServiceRequest $serviceRequest,
        ServiceQuote $serviceQuote,
    ): RedirectResponse {
        abort_unless(Gate::allows('manageAsClient', $serviceRequest), 404, 'Demande introuvable.');
        abort_unless(Gate::allows('view', $serviceQuote), 404, 'Devis introuvable.');

        DB::transaction(function () use ($serviceRequest, $serviceQuote): void {
            $lockedRequest = ServiceRequest::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedQuote = ServiceQuote::query()
                ->whereKey($serviceQuote->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedQuote->service_request_id === (int) $lockedRequest->id, 404, 'Devis introuvable.');
            abort_unless($lockedRequest->service?->pricing_type === 'quote', 409, 'Cette demande n’accepte pas de devis.');
            abort_if($lockedRequest->hasOpenDispute(), 409, 'La demande est gelée pendant le traitement du signalement.');
            abort_unless($lockedQuote->status === 'pending', 409, 'Ce devis ne peut pas être accepté dans son état actuel.');
            abort_unless(in_array($lockedRequest->status, ['pending', 'accepted'], true), 409,
                'La demande ne peut pas accepter de devis dans son état actuel.');
            abort_if($lockedRequest->quotes()->where('status', 'accepted')->exists(), 409,
                'Un devis a déjà été accepté pour cette demande.');

            $lockedRequest->quotes()
                ->whereKeyNot($lockedQuote->getKey())
                ->where('status', 'pending')
                ->update(['status' => 'superseded']);

            abort_unless($lockedQuote->transitionTo(ServiceQuoteStatus::Accepted), 409);
            $lockedQuote->responded_at = now();
            $lockedQuote->save();

            if ($lockedRequest->status === ServiceRequestStatus::Pending->value) {
                abort_unless($lockedRequest->transitionTo(ServiceRequestStatus::Accepted), 409);
            }
            $lockedRequest->save();
        });

        return redirect()
            ->route('client.requests.index')
            ->with('success', 'Le devis a été accepté.');
    }

    /**
     * Refuser un devis proposé pour une demande de type quote.
     */
    public function reject(
        ServiceRequest $serviceRequest,
        ServiceQuote $serviceQuote,
    ): RedirectResponse {
        abort_unless(Gate::allows('manageAsClient', $serviceRequest), 404, 'Demande introuvable.');
        abort_unless(Gate::allows('view', $serviceQuote), 404, 'Devis introuvable.');

        DB::transaction(function () use ($serviceRequest, $serviceQuote): void {
            $lockedRequest = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $lockedQuote = ServiceQuote::query()->whereKey($serviceQuote->id)->lockForUpdate()->firstOrFail();

            abort_unless((int) $lockedQuote->service_request_id === (int) $lockedRequest->id, 404, 'Devis introuvable.');
            abort_unless($lockedRequest->service?->pricing_type === 'quote', 409, 'Cette demande n’accepte pas de devis.');
            abort_unless(in_array($lockedRequest->status, ['pending', 'accepted'], true), 409,
                'La demande ne peut plus traiter ce devis.');
            abort_if($lockedRequest->hasOpenDispute(), 409, 'La demande est gelée pendant le traitement du signalement.');
            abort_unless($lockedQuote->status === 'pending', 409, 'Ce devis ne peut pas être refusé dans son état actuel.');

            abort_unless($lockedQuote->transitionTo(ServiceQuoteStatus::Rejected), 409);
            $lockedQuote->responded_at = now();
            $lockedQuote->save();
        });

        return redirect()
            ->route('client.requests.index')
            ->with('success', 'Le devis a été refusé.');
    }
}
