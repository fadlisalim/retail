<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'user_id', 'order_item_id', 'rating', 'title', 'comment',
        'is_verified_purchase', 'is_visible', 'admin_reply', 'replied_by',
        'helpful_count', 'edit_deadline_at',
    ];

    protected $casts = [
        'is_verified_purchase' => 'boolean',
        'is_visible' => 'boolean',
        'edit_deadline_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class);
    }

    public function helpfulness(): HasMany
    {
        return $this->hasMany(ReviewHelpfulness::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function isEditable(): bool
    {
        return $this->edit_deadline_at !== null && now()->lessThan($this->edit_deadline_at);
    }
}
