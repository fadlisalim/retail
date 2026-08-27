<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'order_id', 'invoice_number', 'public_token', 'subtotal', 'discount',
        'shipping', 'tax', 'total', 'company_snapshot', 'customer_snapshot', 'items_snapshot', 'issued_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'company_snapshot' => 'array',
        'customer_snapshot' => 'array',
        'items_snapshot' => 'array',
        'issued_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    /**
     * Baris barang yang tercetak di dokumen: hasil suntingan admin
     * (items_snapshot) bila ada, selain itu item pesanan apa adanya.
     * Selalu array seragam agar view tidak peduli sumbernya.
     *
     * @return array<int, array{name: string, sku: ?string, quantity: int, unit_price: float, line_total: float}>
     */
    public function lineItems(): array
    {
        if (! empty($this->items_snapshot)) {
            return $this->items_snapshot;
        }

        return ($this->order?->items ?? collect())->map(fn ($item) => [
            'name' => $item->name,
            'sku' => $item->sku,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'line_total' => (float) $item->line_total,
        ])->values()->all();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
