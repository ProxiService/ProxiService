<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_request_id
 * @property string $gross_amount
 * @property string $commission_rate
 * @property string $commission_amount
 * @property string $provider_amount
 * @property string $collection_status
 * @property CarbonImmutable $calculated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'gross_amount',
    'commission_rate',
    'commission_amount',
    'provider_amount',
    'collection_status',
    'calculated_at',
])]
class ServiceCommission extends Model
{
    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'provider_amount' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
