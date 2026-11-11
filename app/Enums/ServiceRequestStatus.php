<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case InProgress = 'in_progress';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Accepted, self::Rejected, self::Cancelled, self::Expired],
            self::Accepted => [self::InProgress, self::Cancelled],
            self::InProgress => [self::AwaitingConfirmation, self::Cancelled],
            self::AwaitingConfirmation => [self::Completed, self::Cancelled],
            self::Rejected, self::Completed, self::Cancelled, self::Expired => [],
        }, true);
    }
}
