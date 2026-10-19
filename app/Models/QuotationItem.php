<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'product_id', 'product_variant_id', 'name', 'note',
        'quantity', 'unit_price', 'unit_cost', 'discount', 'line_total', 'is_taxable',
    ];

    // Modal (HPP) hanya untuk internal: tidak ikut serialisasi (snapshot revisi, JSON, dsb.).
    protected $hidden = ['unit_cost'];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'is_taxable' => 'boolean',
    ];

    /** Total modal baris (null bila modal belum diisi). */
    public function lineCost(): ?float
    {
        return $this->unit_cost === null ? null : round((float) $this->unit_cost * (int) $this->quantity, 2);
    }

    /** Margin baris = jumlah jual − modal (null bila modal belum diisi). */
    public function lineMargin(): ?float
    {
        $cost = $this->lineCost();

        return $cost === null ? null : round((float) $this->line_total - $cost, 2);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
