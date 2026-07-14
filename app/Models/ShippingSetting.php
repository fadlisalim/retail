<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingSetting extends Model
{
    protected $fillable = [
        'packing_fee', 'packing_min_item_grams', 'handling_fee', 'insurance_percent', 'free_shipping_min_subtotal',
        'default_volumetric_divisor', 'weight_rounding_grams',
    ];

    protected $casts = [
        'packing_fee' => 'decimal:2',
        'handling_fee' => 'decimal:2',
        'insurance_percent' => 'decimal:2',
        'free_shipping_min_subtotal' => 'decimal:2',
    ];
}
