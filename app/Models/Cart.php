<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_token', 'coupon_code', 'note', 'last_activity_at'];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->where('saved_for_later', false);
    }

    public function savedItems(): HasMany
    {
        return $this->hasMany(CartItem::class)->where('saved_for_later', true);
    }

    public function allItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
