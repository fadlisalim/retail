<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'public_token', 'user_id',
        'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status',
        'items_subtotal', 'product_discount', 'coupon_discount', 'coupon_code',
        'shipping_cost', 'packing_fee', 'handling_fee', 'insurance_fee',
        'tax_amount', 'grand_total', 'paid_amount',
        'shipping_cost_confirmed', 'shipping_method', 'shipping_service_name', 'billable_weight_grams',
        'payment_method', 'customer_note', 'internal_note', 'idempotency_key',
        'paid_at', 'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'items_subtotal' => 'decimal:2',
        'product_discount' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'packing_fee' => 'decimal:2',
        'handling_fee' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'shipping_cost_confirmed' => 'boolean',
        'status' => OrderStatus::class,
        'payment_status' => PaymentStatus::class,
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function statusEnum(): OrderStatus
    {
        return $this->status instanceof OrderStatus ? $this->status : OrderStatus::from($this->status);
    }

    public function canBeReviewed(): bool
    {
        return $this->statusEnum() === OrderStatus::Completed;
    }
}
