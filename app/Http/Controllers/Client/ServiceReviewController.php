<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Models\ServiceReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
        abort_unless(Gate::allows('manageAsClient', $serviceRequest), 404, 'Demande introuvable.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $serviceRequest, $validated): void {
            $lockedRequest = ServiceRequestModel::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedRequest->client_id === (int) $request->user()->id, 404,
                'Demande introuvable.');
            abort_unless($lockedRequest->status === 'completed', 409,
                'Tu ne peux noter une prestation que lorsqu’elle est terminée.');
            abort_if($lockedRequest->hasOpenDispute(), 409,
                'Tu ne peux pas noter une prestation pendant le traitement d’un signalement.');
            abort_unless($lockedRequest->payments()->where('status', 'completed')->exists(), 409,
                'Un paiement confirmé est nécessaire avant de laisser un avis.');
            abort_if($lockedRequest->review()->exists(), 409,
                'Tu as déjà laissé un avis pour cette prestation.');

            $review = new ServiceReview([
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);
            $review->client()->associate($request->user());
            $review->providerProfile()->associate($lockedRequest->providerProfile);
            $lockedRequest->review()->save($review);
        });

        return redirect()
            ->route('client.requests.index')
            ->with('success', 'Merci ! Ton avis a bien été enregistré.');
    }
}
