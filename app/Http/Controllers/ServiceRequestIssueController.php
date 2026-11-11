<?php

namespace App\Http\Controllers;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceRequestIssueController extends Controller
{
    public function cancel(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $actorId = $this->actorId($request);

        DB::transaction(function () use ($request, $serviceRequest, $validated, $actorId): void {
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $this->authorizeParticipant($request, $locked);

            abort_unless(in_array($locked->status, ['pending', 'accepted', 'in_progress'], true), 409,
                'Cette demande ne peut plus être annulée dans son état actuel.');

            if ($locked->status === ServiceRequestStatus::Pending->value) {
                abort_unless($locked->transitionTo(ServiceRequestStatus::Cancelled), 409);
                $locked->cancellation_reason = $validated['reason'];
                $locked->cancelled_by_user_id = $actorId;
                $locked->cancelled_at = now();
                if ($locked->is_urgent) {
                    $locked->urgent_latitude = null;
                    $locked->urgent_longitude = null;
                }
                $locked->save();

                return;
            }

            // Une annulation après acceptation ou démarrage passe en arbitrage.
            // Aucun frais n'est calculé ni prélevé tant que le flux financier n'est pas validé.
            abort_if($locked->hasOpenDispute(), 409, 'Un signalement est déjà en cours de traitement.');
            $locked->dispute_status = 'open';
            $locked->dispute_reason = 'Demande d’annulation : '.$validated['reason'];
            $locked->disputed_by_user_id = $actorId;
            $locked->disputed_at = now();
            $locked->save();
        });

        return back()->with('success', 'La demande d’annulation a été enregistrée. Les frais éventuels restent bloqués en attente du flux financier.');
    }

    public function dispute(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $actorId = $this->actorId($request);

        DB::transaction(function () use ($request, $serviceRequest, $validated, $actorId): void {
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $this->authorizeParticipant($request, $locked);

            abort_unless(in_array($locked->status, ['accepted', 'in_progress', 'awaiting_confirmation'], true), 409,
                'Un signalement est possible après acceptation et avant la clôture de la demande.');
            abort_if($locked->hasOpenDispute(), 409, 'Un signalement est déjà en cours de traitement.');

            $locked->dispute_status = 'open';
            $locked->dispute_reason = $validated['reason'];
            $locked->disputed_by_user_id = $actorId;
            $locked->disputed_at = now();
            $locked->save();
        });

        return back()->with('success', 'Le signalement est enregistré. Toute décision financière reste bloquée pendant son traitement.');
    }

    public function resolve(
        Request $request,
        ServiceRequest $serviceRequest,
        AdminAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', 'in:resume,cancel'],
            'resolution' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $actorId = $this->actorId($request);

        DB::transaction(function () use ($request, $serviceRequest, $auditLogger, $validated, $actorId): void {
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->hasOpenDispute(), 409, 'Aucun signalement n’est en cours.');

            $before = [
                'status' => $locked->status,
                'dispute_status' => $locked->dispute_status,
            ];

            if ($validated['decision'] === 'cancel') {
                abort_unless($locked->transitionTo(ServiceRequestStatus::Cancelled), 409,
                    'La demande ne peut pas être annulée depuis cet état.');
                $locked->cancellation_reason = $locked->dispute_reason;
                $locked->cancelled_by_user_id = $actorId;
                $locked->cancelled_at = now();
            }

            $locked->dispute_status = 'resolved';
            $locked->dispute_resolution = $validated['resolution'];
            $locked->dispute_resolved_by_user_id = $actorId;
            $locked->dispute_resolved_at = now();
            $locked->save();

            $auditLogger->record($request, 'service_request.dispute_resolved', $locked, $before, [
                ...$before,
                'status' => $locked->status,
                'dispute_status' => $locked->dispute_status,
                'decision' => $validated['decision'],
                'resolution' => $validated['resolution'],
            ]);
        });

        return back()->with('success', 'La décision sur le signalement est enregistrée. Aucun fonds n’a été déplacé.');
    }

    private function authorizeParticipant(Request $request, ServiceRequest $serviceRequest): void
    {
        $user = $request->user();
        $isClient = (int) $serviceRequest->client_id === (int) $user->id;
        $isProvider = (int) $serviceRequest->provider_profile_id === (int) $user->providerProfile?->id;

        abort_unless($isClient || $isProvider, 404, 'Demande introuvable.');
    }

    /** @return positive-int */
    private function actorId(Request $request): int
    {
        $id = (int) $request->user()->getAuthIdentifier();
        if ($id < 1) {
            abort(401);
        }

        return $id;
    }
}
