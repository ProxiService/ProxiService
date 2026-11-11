<?php

namespace App\Policies;

use App\Models\ServicePayment;
use App\Models\User;

class ServicePaymentPolicy
{
    public function view(User $user, ServicePayment $payment): bool
    {
        return $user->isAdmin()
            || (int) $payment->client_id === (int) $user->id
            || ($user->isPrestataire() && (int) $payment->serviceRequest?->provider_profile_id === (int) $user->providerProfile?->id);
    }
}
