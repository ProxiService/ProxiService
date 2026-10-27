<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $paid_at
 */
#[Fillable([
    'provider',
    'provider_reference',
    'provider_transaction_id',
    'amount',
    'currency',
    'fees',
    'net_amount',
    'payment_method',
    'status',
    'checkout_url',
    'metadata',
    'failure_reason',
    'paid_at',
    'environment',
])]
class ServicePayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fees' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
