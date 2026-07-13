<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    protected $fillable = ['product_id', 'attribute_id', 'value_text', 'value_number'];

    protected $casts = ['value_number' => 'decimal:4'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function displayValue(): string
    {
        $value = $this->value_text ?? rtrim(rtrim((string) $this->value_number, '0'), '.');

        return trim($value.' '.($this->attribute->unit ?? ''));
    }
}
