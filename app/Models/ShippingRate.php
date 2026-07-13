<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = [
        'shipping_service_id', 'shipping_zone_id', 'price_per_kg', 'min_price', 'base_price',
    ];

    protected $casts = [
        'price_per_kg' => 'decimal:2',
        'min_price' => 'decimal:2',
        'base_price' => 'decimal:2',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ShippingService::class, 'shipping_service_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
