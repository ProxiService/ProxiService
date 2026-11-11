<?php

namespace App\Policies;

use App\Models\ServiceQuote;
use App\Models\User;

class ServiceQuotePolicy
{
    public function view(User $user, ServiceQuote $quote): bool
    {
        $request = $quote->serviceRequest;

        return $user->isAdmin()
            || ($user->isClient() && (int) $request?->client_id === (int) $user->id)
            || ($user->isPrestataire() && (int) $quote->provider_profile_id === (int) $user->providerProfile?->id);
    }
}
