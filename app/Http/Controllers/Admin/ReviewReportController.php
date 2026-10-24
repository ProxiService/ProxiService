<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReviewReportController extends Controller
{
    /**
     * Afficher les signalements en attente.
     */
    public function index(): Response
    {
        $reports = ServiceReviewReport::query()
            ->where('status', 'pending')
            ->with([
                'reporter:id,name',
                'review:id,service_request_id,client_id,provider_profile_id,rating,comment,is_visible,created_at',
                'review.client:id,name',
                'review.providerProfile:id,user_id,business_name',
                'review.providerProfile.user:id,name',
                'review.serviceRequest:id,service_id',
                'review.serviceRequest.service:id,name',
            ])
            ->latest()
            ->paginate(10)
            ->through(fn (ServiceReviewReport $report): array => [
                'id' => $report->id,
                'reason' => $report->reason,
                'details' => $report->details,
                'created_at' => $report->created_at?->format('d/m/Y H:i'),
                'reason_label' => match ($report->reason) {
                    'spam' => 'Spam',
                    'abusive' => 'Contenu abusif',
                    'false_information' => 'Informations fausses',
                    'conflict_of_interest' => 'Conflit d’intérêts',
                    default => 'Autre problème',
                },
                'reporter' => [
                    'name' => $report->reporter->name,
                ],
                'review' => [
                    'id' => $report->review->id,
                    'rating' => $report->review->rating,
                    'comment' => $report->review->comment,
                    'client_name' => $report->review->client->name,
                    'provider_name' => $report->review->providerProfile
                        ->business_name
                        ?: $report->review->providerProfile->user->name,
                    'service_name' => $report->review->serviceRequest
                        ->service->name,
                ],
            ]);

        return Inertia::render('admin/review-reports/index', [
            'reports' => $reports,
        ]);
    }

    /**
     * Rejeter un signalement sans masquer l’avis.
     */
    public function dismiss(
        ServiceReviewReport $serviceReviewReport,
    ): RedirectResponse {
        abort_unless(
            $serviceReviewReport->status === 'pending',
            409,
            'Ce signalement a déjà été traité.',
        );

        $serviceReviewReport->status = 'dismissed';
        $serviceReviewReport->save();

        return redirect()
            ->route('admin.review-reports.index')
            ->with('success', 'Le signalement a été rejeté.');
    }

    /**
     * Masquer l’avis et traiter les signalements encore en attente.
     */
    public function hideReview(
        ServiceReviewReport $serviceReviewReport,
    ): RedirectResponse {
        abort_unless(
            $serviceReviewReport->status === 'pending',
            409,
            'Ce signalement a déjà été traité.',
        );

        DB::transaction(function () use ($serviceReviewReport): void {
            $review = $serviceReviewReport->review;

            $review->is_visible = false;
            $review->save();

            $review->reports()
                ->where('status', 'pending')
                ->update(['status' => 'actioned']);
        });

        return redirect()
            ->route('admin.review-reports.index')
            ->with('success', 'L’avis a été masqué.');
    }
}
