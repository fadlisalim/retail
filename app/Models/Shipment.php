<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'service', 'tracking_number', 'status',
        'billable_weight_grams', 'cost', 'proof_path', 'shipped_at', 'delivered_at',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }
}
