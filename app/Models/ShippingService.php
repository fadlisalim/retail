<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingService extends Model
{
    protected $fillable = [
        'shipping_provider_id', 'code', 'name', 'type', 'volumetric_divisor',
        'min_weight_grams', 'max_weight_grams', 'estimated_days', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'shipping_provider_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }
}
