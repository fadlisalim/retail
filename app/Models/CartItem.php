<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'product_id', 'product_variant_id', 'quantity',
        'unit_price_snapshot', 'saved_for_later', 'condition_acknowledged',
    ];

    protected $casts = [
        'unit_price_snapshot' => 'decimal:2',
        'saved_for_later' => 'boolean',
        'condition_acknowledged' => 'boolean',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Current authoritative unit price (variant overrides product). */
    public function currentUnitPrice(): float
    {
        return $this->variant
            ? $this->variant->effectivePrice()
            : $this->product->effectivePrice();
    }

    public function lineWeightGrams(): int
    {
        $unit = $this->variant ? $this->variant->weightGrams() : (int) $this->product->weight_grams;

        return $unit * $this->quantity;
    }
}
