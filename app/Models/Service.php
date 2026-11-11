<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @property-read ProviderProfileService $pivot */
#[Fillable([
    'category_id',
    'name',
    'slug',
    'description',
    'pricing_type',
    'base_price',
    'estimated_duration_minutes',
    'is_active',
])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'estimated_duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<ProviderProfile, $this, ProviderProfileService>
     */
    public function providerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            ProviderProfile::class,
            'provider_profile_service'
        )->using(ProviderProfileService::class)
            ->withPivot(['price', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Resolve the price for a specific provider, falling back to base price.
     */
    public function getPriceForProvider(int $providerProfileId): float
    {
        $providerService = $this->providerProfiles()
            ->where('provider_profile_id', $providerProfileId)
            ->first();

        $price = $providerService?->pivot->price ?? $this->base_price;

        return (float) $price;
    }
}
