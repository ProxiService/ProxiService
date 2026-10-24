<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceQuoteController extends Controller
{
    /**
     * Accepter un devis proposé pour une demande de type quote.
     */
    public function accept(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceQuote $serviceQuote,
    ): RedirectResponse {
        $this->assertOwnRequest($request, $serviceRequest);

        abort_unless(
            $serviceRequest->service?->pricing_type === 'quote',
            409,
            'Cette demande n’accepte pas de devis.',
        );

        abort_unless(
            (int) $serviceQuote->service_request_id === (int) $serviceRequest->id,
            404,
            'Devis introuvable.',
        );

        abort_unless(
            $serviceQuote->status === 'pending',
            409,
            'Ce devis ne peut pas être accepté dans son état actuel.',
        );

        DB::transaction(function () use ($serviceRequest, $serviceQuote) {
            $serviceRequest->quotes()
                ->whereKeyNot($serviceQuote->getKey())
                ->whereIn('status', ['pending', 'accepted'])
                ->update(['status' => 'rejected']);

            $serviceQuote->status = 'accepted';
            $serviceQuote->responded_at = now();
            $serviceQuote->save();

            $serviceRequest->status = 'accepted';
            $serviceRequest->save();
        });

        return redirect()
            ->route('client.requests.index')
            ->with('success', 'Le devis a été accepté.');
    }

    /**
     * Refuser un devis proposé pour une demande de type quote.
     */
    public function reject(
        Request $request,
        ServiceRequest $serviceRequest,
        ServiceQuote $serviceQuote,
    ): RedirectResponse {
        $this->assertOwnRequest($request, $serviceRequest);

        abort_unless(
            $serviceRequest->service?->pricing_type === 'quote',
            409,
            'Cette demande n’accepte pas de devis.',
        );

        abort_unless(
            (int) $serviceQuote->service_request_id === (int) $serviceRequest->id,
            404,
            'Devis introuvable.',
        );

        abort_unless(
            $serviceQuote->status === 'pending',
            409,
            'Ce devis ne peut pas être refusé dans son état actuel.',
        );

        $serviceQuote->status = 'rejected';
        $serviceQuote->responded_at = now();
        $serviceQuote->save();

        return redirect()
            ->route('client.requests.index')
            ->with('success', 'Le devis a été refusé.');
    }

    /**
     * Vérifier que la demande appartient au client connecté.
     */
    private function assertOwnRequest(
        Request $request,
        ServiceRequest $serviceRequest,
    ): void {
        abort_unless(
            (int) $serviceRequest->client_id === (int) $request->user()->id,
            404,
            'Demande introuvable.',
        );
    }
}
