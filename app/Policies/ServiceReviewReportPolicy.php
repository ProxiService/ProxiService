<?php

namespace App\Policies;

use App\Models\ServiceReviewReport;
use App\Models\User;

class ServiceReviewReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ServiceReviewReport $report): bool
    {
        return $user->isAdmin() || (int) $report->reporter_id === (int) $user->id;
    }
}
