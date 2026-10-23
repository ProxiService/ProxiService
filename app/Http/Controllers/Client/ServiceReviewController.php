<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\ServiceReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceReviewController extends Controller
{
    /**
     * Enregistrer l'avis du client après une prestation terminée.
     */
    public function store(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        // Seul le client propriétaire de la demande peut laisser un avis.
        abort_unless(
            (int) $serviceRequest->client_id === (int) $request->user()->id,
            404,
            'Demande introuvable.',
        );

        // La prestation doit avoir été confirmée comme terminée.
        abort_unless(
            $serviceRequest->status === 'completed',
            409,
            'Tu ne peux noter une prestation que lorsqu’elle est terminée.',
        );

        // Une demande ne peut recevoir qu'un seul avis.
        abort_if(
            $serviceRequest->review()->exists(),
            409,
            'Tu as déjà laissé un avis pour cette prestation.',
        );

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = new ServiceReview([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        // Les relations sont déterminées par la demande et l'utilisateur.
        $review->client()->associate($request->user());
        $review->providerProfile()->associate(
            $serviceRequest->providerProfile,
        );

        $serviceRequest->review()->save($review);

        return redirect()
            ->route('client.requests.index')
            ->with('success', 'Merci ! Ton avis a bien été enregistré.');
    }
}
