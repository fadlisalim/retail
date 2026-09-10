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
        'attributed_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'status' => CommissionStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** Admin yang mengaitkan afiliator secara manual (null = command/server). */
    public function attributedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attributed_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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
