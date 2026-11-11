<?php

namespace App\Models;

use App\Enums\ServiceQuoteStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_request_id
 * @property int $provider_profile_id
 * @property string $amount
 * @property string $description
 * @property string $status
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'service_request_id',
    'provider_profile_id',
    'amount',
    'description',
    'status',
    'responded_at',
])]
class ServiceQuote extends Model
{
    public function transitionTo(ServiceQuoteStatus $next): bool
    {
        $current = ServiceQuoteStatus::tryFrom($this->status);

        if ($current === null || ! $current->canTransitionTo($next)) {
            return false;
        }

        $this->status = $next->value;

        return true;
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<ProviderProfile, $this>
     */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }
}
