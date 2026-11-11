<?php

namespace App\Services;

use App\Enums\ServiceRequestStatus;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Notifications\NewServiceRequestNotification;
use Illuminate\Support\Facades\DB;

class UrgentRequestDispatcher
{
    public const INITIAL_RADIUS_KM = 5;

    public const EXTENDED_RADIUS_KM = 10;

    public const EXTEND_AFTER_MINUTES = 5;

    public const EXPIRE_AFTER_MINUTES = 15;

    public function __construct(private readonly ProviderDistance $distance) {}

    public function dispatch(ServiceRequest $serviceRequest, int $radiusKm): int
    {
        if (! $serviceRequest->is_urgent || $serviceRequest->status !== 'pending') {
            return 0;
        }

        $matches = $serviceRequest->service->providerProfiles()
            ->where('provider_profiles.status', 'approved')
            ->where('provider_profiles.is_available', true)
            ->whereNotNull('provider_profiles.latitude')
            ->whereNotNull('provider_profiles.longitude')
            ->wherePivot('is_active', true)
            ->with('user:id,name,role')
            ->get()
            ->filter(function (ProviderProfile $profile) use ($serviceRequest, $radiusKm): bool {
                if ($profile->user?->role !== 'prestataire') {
                    return false;
                }

                $kilometers = $this->distance->kilometers(
                    (float) $serviceRequest->urgent_latitude,
                    (float) $serviceRequest->urgent_longitude,
                    (float) $profile->latitude,
                    (float) $profile->longitude,
                );

                return $kilometers <= $radiusKm;
            });

        $newMatches = 0;
        foreach ($matches as $profile) {
            $created = DB::table('urgent_service_request_matches')->insertOrIgnore([
                'service_request_id' => $serviceRequest->id,
                'provider_profile_id' => $profile->id,
                'matched_radius_km' => $radiusKm,
                'notified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($created !== 1) {
                continue;
            }

            $profile->user->notify(new NewServiceRequestNotification(
                serviceRequestId: (int) $serviceRequest->id,
                serviceName: $serviceRequest->service->name,
                clientName: 'Un client',
            ));
            $newMatches++;
        }

        return $newMatches;
    }

    public function dispatchDue(): void
    {
        ServiceRequest::query()
            ->where('is_urgent', true)
            ->where('status', 'pending')
            ->whereNotNull('urgent_expires_at')
            ->with('service')
            ->orderBy('id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $serviceRequest) {
                    DB::transaction(function () use ($serviceRequest): void {
                        $locked = ServiceRequest::query()->whereKey($serviceRequest->id)
                            ->lockForUpdate()->first();
                        if (! $locked || $locked->status !== 'pending') {
                            return;
                        }
                        $locked->loadMissing('service');
                        $ageMinutes = $locked->created_at?->diffInMinutes(now()) ?? 0;

                        if ($locked->urgent_expires_at && $locked->urgent_expires_at->lte(now())) {
                            $hasCandidates = DB::table('urgent_service_request_matches')
                                ->where('service_request_id', $locked->id)->exists();
                            $locked->transitionTo(ServiceRequestStatus::Expired);
                            $locked->urgent_dispatch_status = $hasCandidates ? 'expired' : 'no_candidates';
                            $locked->urgent_latitude = null;
                            $locked->urgent_longitude = null;
                            $locked->save();

                            return;
                        }

                        if ($ageMinutes >= self::EXTEND_AFTER_MINUTES && ! $locked->urgent_expanded_at) {
                            $this->dispatch($locked, self::EXTENDED_RADIUS_KM);
                            $locked->urgent_expanded_at = now();
                            $locked->save();
                        }
                    });
                }
            });
    }
}
