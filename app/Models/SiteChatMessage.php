<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in the on-site "Chat Toko" (customer ↔ admin, web-only).
 * direction 'in' = customer → store, 'out' = admin → customer.
 */
class SiteChatMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id', 'user_id', 'admin_id', 'direction', 'message',
        'product_slug', 'is_read', 'created_at',
    ];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
