<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'name', 'option_values', 'price', 'sale_price', 'stock',
        'weight_grams', 'length_cm', 'width_cm', 'height_cm', 'image_path', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'option_values' => 'array',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price > 0
            && (float) $this->sale_price < (float) $this->effectiveBasePrice();
    }

    /** Falls back to the parent product price when the variant has none. */
    public function effectiveBasePrice(): float
    {
        return (float) ($this->price ?? $this->product->price);
    }

    public function effectivePrice(): float
    {
        if ($this->sale_price !== null && (float) $this->sale_price > 0) {
            return (float) $this->sale_price;
        }

        return $this->price !== null ? (float) $this->price : $this->product->effectivePrice();
    }

    public function weightGrams(): int
    {
        return (int) ($this->weight_grams ?? $this->product->weight_grams);
    }

    public function lengthCm(): float
    {
        return (float) ($this->length_cm ?? $this->product->length_cm);
    }

    public function widthCm(): float
    {
        return (float) ($this->width_cm ?? $this->product->width_cm);
    }

    public function heightCm(): float
    {
        return (float) ($this->height_cm ?? $this->product->height_cm);
    }

    /** Per-unit shipping volume (cm³), using variant dimensions when set. */
    public function volumeCm3(): float
    {
        return $this->lengthCm() * $this->widthCm() * $this->heightCm();
    }
}
