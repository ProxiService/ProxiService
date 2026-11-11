<?php

namespace App\Policies;

use App\Models\ProviderProfile;
use App\Models\User;

class ProviderProfilePolicy
{
    public function view(User $user, ProviderProfile $profile): bool
    {
        return $user->isAdmin()
            || (int) $profile->user_id === (int) $user->id
            || $profile->status === 'approved';
    }

    public function update(User $user, ProviderProfile $profile): bool
    {
        return $user->isPrestataire() && (int) $profile->user_id === (int) $user->id;
    }
}
