<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductConditionDetail extends Model
{
    protected $fillable = [
        'product_id', 'reason_for_sale', 'item_location', 'available_quantity',
        'purchase_year', 'remaining_warranty', 'completeness', 'defect_notes',
        'is_returnable', 'is_negotiable', 'pickup_required', 'auto_shipping', 'actual_condition_photos',
    ];

    protected $casts = [
        'is_returnable' => 'boolean',
        'is_negotiable' => 'boolean',
        'pickup_required' => 'boolean',
        'auto_shipping' => 'boolean',
        'actual_condition_photos' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
