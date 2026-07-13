<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'order_id', 'invoice_number', 'public_token', 'subtotal', 'discount',
        'shipping', 'tax', 'total', 'company_snapshot', 'customer_snapshot', 'issued_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'company_snapshot' => 'array',
        'customer_snapshot' => 'array',
        'issued_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
