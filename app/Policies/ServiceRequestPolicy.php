<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function view(User $user, ServiceRequest $request): bool
    {
        return $user->isAdmin()
            || ($user->isClient() && (int) $request->client_id === (int) $user->id)
            || ($user->isPrestataire() && (
                (int) $request->provider_profile_id === (int) $user->providerProfile?->id
                || ($request->is_urgent && $request->status === 'pending'
                    && $user->providerProfile?->urgentRequestMatches()->where('service_request_id', $request->id)->exists())
            ));
    }

    public function message(User $user, ServiceRequest $request): bool
    {
        return $this->view($user, $request)
            && in_array($request->status, ['accepted', 'in_progress', 'awaiting_confirmation', 'completed'], true);
    }

    public function manageAsClient(User $user, ServiceRequest $request): bool
    {
        return $user->isClient() && (int) $request->client_id === (int) $user->id;
    }
}
