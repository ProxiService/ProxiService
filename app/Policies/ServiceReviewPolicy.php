<?php

namespace App\Policies;

use App\Models\ServiceReview;
use App\Models\User;

class ServiceReviewPolicy
{
    public function view(User $user, ServiceReview $review): bool
    {
        return $review->is_visible
            || $user->isAdmin()
            || (int) $review->client_id === (int) $user->id
            || ($user->isPrestataire() && (int) $review->provider_profile_id === (int) $user->providerProfile?->id);
    }

    public function report(User $user, ServiceReview $review): bool
    {
        return $review->is_visible;
    }
}
