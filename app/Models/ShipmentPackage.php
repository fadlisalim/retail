<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentPackage extends Model
{
    protected $fillable = [
        'shipment_id', 'actual_weight_grams', 'volumetric_weight_grams',
        'billable_weight_grams', 'length_cm', 'width_cm', 'height_cm',
    ];

    protected $casts = [
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
