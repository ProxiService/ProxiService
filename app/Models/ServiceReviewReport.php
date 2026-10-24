<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reason', 'details'])]
class ServiceReviewReport extends Model
{
    /**
     * @return BelongsTo<ServiceReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ServiceReview::class, 'service_review_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
