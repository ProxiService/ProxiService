<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read ProviderProfileService $pivot
 * @property-read int $visible_reviews_count
 * @property-read float|null $visible_reviews_avg_rating
 */
#[Fillable([
    'business_name',
    'bio',
    'phone',
    'years_experience',
    'city',
    'neighborhood',
    'address',
    'latitude',
    'longitude',
    'is_available',
])]
class ProviderProfile extends Model
{
    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Service, $this, ProviderProfileService>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            Service::class,
            'provider_profile_service'
        )->using(ProviderProfileService::class)
            ->withPivot(['price', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ServiceReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ServiceReview::class);
    }

    /** @return HasMany<UrgentServiceRequestMatch, $this> */
    public function urgentRequestMatches(): HasMany
    {
        return $this->hasMany(UrgentServiceRequestMatch::class);
    }
}
