<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $scheduled_at
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
        ];
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
     * @return HasOne<ServiceReview, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(ServiceReview::class);
    }
}
