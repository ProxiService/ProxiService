<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewReportRequest;
use App\Models\ServiceReview;
use App\Models\ServiceReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReviewReportController extends Controller
{
    public function store(
        StoreReviewReportRequest $request,
        ServiceReview $serviceReview,
    ): RedirectResponse {
        abort_unless(Gate::allows('report', $serviceReview), 404);
        abort_if(
            (int) $serviceReview->client_id === (int) $request->user()->id,
            403,
            'Tu ne peux pas signaler ton propre avis.',
        );

        abort_if(
            $serviceReview->reports()
                ->where('reporter_id', $request->user()->id)
                ->exists(),
            409,
            'Tu as déjà signalé cet avis.',
        );

        $validated = $request->validated();

        $report = new ServiceReviewReport([
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
        ]);

        $report->review()->associate($serviceReview);
        $report->reporter()->associate($request->user());
        $report->save();

        return back()->with(
            'success',
            'Merci. Ton signalement a été transmis à l’administration.',
        );
    }
}
