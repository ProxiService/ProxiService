<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $disputed_at
 * @property CarbonImmutable|null $dispute_resolved_at
 * @property string|null $dispute_status
 * @property string|null $dispute_reason
 * @property string|null $dispute_resolution
 * @property int|null $cancelled_by_user_id
 * @property int|null $disputed_by_user_id
 * @property int|null $dispute_resolved_by_user_id
 * @property float|null $urgent_latitude
 * @property float|null $urgent_longitude
 * @property CarbonImmutable|null $urgent_expires_at
 * @property CarbonImmutable|null $urgent_expanded_at
 * @property string|null $urgent_dispatch_status
 */
#[Fillable([
    'description',
    'city',
    'neighborhood',
    'address',
    'scheduled_at',
    'is_urgent',
])]
class ServiceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_urgent' => 'boolean',
            'cancelled_at' => 'datetime',
            'disputed_at' => 'datetime',
            'dispute_resolved_at' => 'datetime',
            'urgent_latitude' => 'float',
            'urgent_longitude' => 'float',
            'urgent_expires_at' => 'datetime',
            'urgent_expanded_at' => 'datetime',
        ];
    }

    public function transitionTo(ServiceRequestStatus $next): bool
    {
        $current = ServiceRequestStatus::tryFrom($this->status);

        if ($current === null || ! $current->canTransitionTo($next)) {
            return false;
        }

        $this->status = $next->value;

        return true;
    }

    public function hasOpenDispute(): bool
    {
        return $this->dispute_status === 'open';
    }

    /** @return HasMany<UrgentServiceRequestMatch, $this> */
    public function urgentMatches(): HasMany
    {
        return $this->hasMany(UrgentServiceRequestMatch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<ProviderProfile, $this>
     */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<ServiceRequestMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ServiceRequestMessage::class)
            ->orderBy('created_at');
    }

    /**
     * @return HasMany<ServiceQuote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(ServiceQuote::class);
    }

    /**
     * @return HasMany<ServicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ServicePayment::class)->latest();
    }

    /**
     * @return HasOne<ServiceQuote, $this>
     */
    public function acceptedQuote(): HasOne
    {
        return $this->hasOne(ServiceQuote::class)->where('status', 'accepted');
    }

    /**
     * @return HasOne<ServiceReview, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(ServiceReview::class);
    }

    /**
     * @return HasOne<ServiceCommission, $this>
     */
    public function commission(): HasOne
    {
        return $this->hasOne(ServiceCommission::class);
    }
}
