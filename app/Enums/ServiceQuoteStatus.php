<?php

namespace App\Enums;

enum ServiceQuoteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    public function canTransitionTo(self $next): bool
    {
        return $this === self::Pending
            && in_array($next, [self::Accepted, self::Rejected, self::Superseded], true);
    }
}
