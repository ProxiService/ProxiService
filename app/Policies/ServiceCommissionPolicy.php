<?php

namespace App\Policies;

use App\Models\ServiceCommission;
use App\Models\User;

class ServiceCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ServiceCommission $commission): bool
    {
        return $user->isAdmin()
            || (int) $commission->serviceRequest?->client_id === (int) $user->id
            || ($user->isPrestataire() && (int) $commission->serviceRequest?->provider_profile_id === (int) $user->providerProfile?->id);
    }
}
