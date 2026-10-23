<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ServiceRequestMessage::class)
            ->orderBy('created_at');
    }
}
