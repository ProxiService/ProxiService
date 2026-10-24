<?php

namespace App\Http\Controllers;

use App\Models\ServiceReview;
use App\Models\ServiceReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewReportController extends Controller
{
    public function store(
        Request $request,
        ServiceReview $serviceReview,
    ): RedirectResponse {
        abort_unless($serviceReview->is_visible, 404);

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

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                Rule::in([
                    'spam',
                    'abusive',
                    'false_information',
                    'conflict_of_interest',
                    'other',
                ]),
            ],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

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
