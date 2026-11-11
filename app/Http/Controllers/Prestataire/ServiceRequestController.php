<?php

namespace App\Http\Controllers\Prestataire;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Models\ServiceQuote;
use App\Models\ServiceRequest as ServiceRequestModel;
use App\Notifications\ServiceRequestStatusNotification;
use App\Services\CommissionCalculator;
use App\Services\ProviderDistance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    /**
     * Afficher les demandes reçues par le prestataire connecté.
     */
    public function index(Request $request, CommissionCalculator $commissionCalculator): Response
    {
        $providerProfileId = $request->user()->providerProfile()->value('id');
        $view = $request->query('view') === 'messages' ? 'messages' : 'requests';

        $query = ServiceRequestModel::query()
            ->where(function ($query) use ($providerProfileId): void {
                $query->where('provider_profile_id', $providerProfileId ?? 0)
                    ->orWhereHas('urgentMatches', fn ($matches) => $matches->where('provider_profile_id', $providerProfileId ?? 0));
            });

        if ($view === 'messages') {
            $query->whereIn('status', ['accepted', 'in_progress', 'awaiting_confirmation', 'completed']);
        }

        $requests = $query
            ->with([
                'client:id,name',
                'service:id,name,slug,pricing_type',
                'quotes:id,service_request_id,amount,description,status,responded_at,created_at',
                'payments:id,service_request_id,status',
            ])
            ->latest()
            ->paginate(10)
            ->through(
                fn (ServiceRequestModel $serviceRequest): array => [
                    'id' => $serviceRequest->id,
                    'description' => $serviceRequest->description,
                    'city' => $serviceRequest->city,
                    'neighborhood' => $serviceRequest->neighborhood,
                    'address' => $serviceRequest->payments->contains(
                        fn ($payment): bool => $payment->status === 'completed',
                    ) ? $serviceRequest->address : null,
                    'scheduled_at' => $serviceRequest->scheduled_at
                        ?->format('d/m/Y H:i'),
                    'is_urgent' => $serviceRequest->is_urgent,
                    'urgent_dispatch_status' => $serviceRequest->urgent_dispatch_status,
                    'urgent_expires_at' => $serviceRequest->urgent_expires_at?->format('d/m/Y H:i'),
                    'status' => $serviceRequest->status,
                    'dispute_status' => $serviceRequest->dispute_status,
                    'dispute_reason' => $serviceRequest->dispute_reason,
                    'dispute_resolution' => $serviceRequest->dispute_resolution,
                    'created_at' => $serviceRequest->created_at
                        ?->format('d/m/Y H:i'),
                    'client' => [
                        'name' => $serviceRequest->client->name,
                    ],
                    'service' => [
                        'name' => $serviceRequest->service->name,
                        'slug' => $serviceRequest->service->slug,
                        'pricing_type' => $serviceRequest->service->pricing_type,
                    ],
                    'quotes' => $serviceRequest->quotes
                        ->map(fn (ServiceQuote $quote): array => [
                            'id' => $quote->id,
                            'amount' => $quote->amount,
                            'description' => $quote->description,
                            'status' => $quote->status,
                            'created_at' => $quote->created_at?->format('d/m/Y H:i'),
                            'responded_at' => $quote->responded_at?->format('d/m/Y H:i'),
                            'commission' => $commissionCalculator->breakdown((float) $quote->amount),
                        ])
                        ->values(),
                ],
            );

        return Inertia::render('prestataire/requests/index', [
            'requests' => $requests,
            'commissionRate' => (float) config('proxiservices.commission_rate', 10.0),
            'view' => $view,
        ]);
    }

    /** Première acceptation d'une demande urgente et dépôt du devis dans une seule transaction. */
    public function acceptUrgent(
        Request $request,
        ServiceRequestModel $serviceRequest,
        ProviderDistance $distance,
    ): RedirectResponse {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $serviceRequest, $validated, $distance): void {
            $locked = ServiceRequestModel::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $profile = $request->user()->providerProfile;
            abort_unless($profile && $locked->is_urgent
                && $profile->urgentRequestMatches()->where('service_request_id', $locked->id)->exists(), 404);
            abort_unless($locked->status === 'pending'
                && $locked->urgent_expires_at?->gt(now()), 409,
                'Cette demande urgente a déjà été attribuée ou a expiré.');

            $profile = ProviderProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            abort_unless($profile->status === 'approved' && $profile->is_available
                && $profile->latitude !== null && $profile->longitude !== null
                && $profile->services()->where('services.id', $locked->service_id)->wherePivot('is_active', true)->exists(), 409,
                'Votre profil doit rester approuvé, disponible et positionné pour accepter.');
            $match = DB::table('urgent_service_request_matches')
                ->where('service_request_id', $locked->id)
                ->where('provider_profile_id', $profile->id)->first();
            abort_unless($match && $distance->kilometers(
                (float) $locked->urgent_latitude,
                (float) $locked->urgent_longitude,
                (float) $profile->latitude,
                (float) $profile->longitude,
            ) <= (int) $match->matched_radius_km, 409,
                'Votre profil n’est plus dans le rayon de diffusion de cette demande.');

            abort_unless($locked->transitionTo(ServiceRequestStatus::Accepted), 409);
            $locked->provider_profile_id = $profile->id;
            $locked->urgent_dispatch_status = 'assigned';
            $locked->urgent_latitude = null;
            $locked->urgent_longitude = null;
            $locked->save();

            ServiceQuote::create([
                'service_request_id' => $locked->id,
                'provider_profile_id' => $profile->id,
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'status' => 'pending',
            ]);

            DB::table('urgent_service_request_matches')
                ->where('service_request_id', $locked->id)
                ->where('provider_profile_id', $profile->id)
                ->update(['accepted_at' => now(), 'updated_at' => now()]);
        });

        return to_route('prestataire.requests.index')->with('success', 'Vous avez accepté la demande urgente et envoyé votre devis.');
    }

    /**
     * Accepter une demande en attente.
     */
    public function accept(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $serviceRequest = DB::transaction(function () use ($request, $serviceRequest): ServiceRequestModel {
            $locked = ServiceRequestModel::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $this->ensureOwnRequest($request, $locked);
            abort_unless($locked->transitionTo(ServiceRequestStatus::Accepted), 409,
                'Cette demande a déjà été traitée.');
            $locked->save();

            return $locked;
        });
        $this->notifyClientOfStatus($request, $serviceRequest, 'accepted');

        return redirect()
            ->route('prestataire.requests.index')
            ->with('success', 'La demande a été acceptée.');
    }

    /**
     * Refuser une demande en attente.
     */
    public function reject(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $serviceRequest = DB::transaction(function () use ($request, $serviceRequest): ServiceRequestModel {
            $locked = ServiceRequestModel::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $this->ensureOwnRequest($request, $locked);
            abort_unless($locked->transitionTo(ServiceRequestStatus::Rejected), 409,
                'Cette demande a déjà été traitée.');
            $locked->save();

            return $locked;
        });
        $this->notifyClientOfStatus($request, $serviceRequest, 'rejected');

        return redirect()
            ->route('prestataire.requests.index')
            ->with('success', 'La demande a été refusée.');
    }

    /**
     * Notifier le client d'un changement de statut.
     */
    private function notifyClientOfStatus(
        Request $request,
        ServiceRequestModel $serviceRequest,
        string $status,
    ): void {
        $serviceRequest->loadMissing([
            'client:id,name',
            'service:id,name',
        ]);

        $serviceRequest->client->notify(
            new ServiceRequestStatusNotification(
                serviceRequestId: (int) $serviceRequest->id,
                serviceName: $serviceRequest->service->name,
                status: $status,
                actorName: $request->user()->name,
                recipientRole: 'client',
            ),
        );
    }

    /**
     * Vérifier que la demande appartient au prestataire connecté.
     */
    private function ensureOwnRequest(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): void {
        $providerProfile = $request->user()->providerProfile;

        abort_unless(
            $providerProfile
            && (int) $serviceRequest->provider_profile_id === (int) $providerProfile->id,
            404,
            'Demande introuvable.',
        );
    }

    /**
     * Indiquer que l'intervention a commencé.
     */
    public function start(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $serviceRequest = DB::transaction(function () use ($request, $serviceRequest): ServiceRequestModel {
            $locked = ServiceRequestModel::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $this->ensureOwnRequest($request, $locked);
            abort_if($locked->hasOpenDispute(), 409,
                'Cette demande est gelée pendant le traitement du signalement.');
            $locked->load('service');

            if ($locked->service?->pricing_type === 'quote') {
                abort_unless($locked->quotes()->where('status', 'accepted')->exists(), 409,
                    'Un devis accepté est requis avant de démarrer cette intervention.');
            }

            abort_unless($locked->payments()->where('status', 'completed')->exists(), 409,
                'Le client doit avoir réglé la prestation avant son démarrage.');
            abort_unless($locked->transitionTo(ServiceRequestStatus::InProgress), 409,
                'Cette demande ne peut pas démarrer depuis son état actuel.');
            $locked->save();

            return $locked;
        });
        $this->notifyClientOfStatus($request, $serviceRequest, 'in_progress');

        return redirect()
            ->route('prestataire.requests.index')
            ->with('success', 'L’intervention a commencé.');
    }

    /**
     * Signaler que l'intervention est terminée.
     */
    public function finish(
        Request $request,
        ServiceRequestModel $serviceRequest,
    ): RedirectResponse {
        $serviceRequest = DB::transaction(function () use ($request, $serviceRequest): ServiceRequestModel {
            $locked = ServiceRequestModel::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $this->ensureOwnRequest($request, $locked);
            abort_if($locked->hasOpenDispute(), 409,
                'Cette demande est gelée pendant le traitement du signalement.');
            abort_unless($locked->transitionTo(ServiceRequestStatus::AwaitingConfirmation), 409,
                'Cette demande ne peut pas être terminée depuis son état actuel.');
            $locked->save();

            return $locked;
        });
        $this->notifyClientOfStatus(
            $request,
            $serviceRequest,
            'awaiting_confirmation',
        );

        return redirect()
            ->route('prestataire.requests.index')
            ->with(
                'success',
                'La fin de l’intervention a été signalée au client.',
            );
    }
}
