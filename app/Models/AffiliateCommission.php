<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $fillable = [
        'affiliate_id', 'order_id', 'order_item_id', 'product_id',
        'base_amount', 'rate', 'amount', 'status',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'status' => CommissionStatus::class,
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
