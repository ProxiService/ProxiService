<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property-read string|null $price
 * @property-read bool $is_active
 */
class ProviderProfileService extends Pivot
{
    protected $table = 'provider_profile_service';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
