<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A single WhatsApp message (in/out) in the admin inbox. Grouped by phone. */
class WaMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'phone', 'name', 'direction', 'message', 'wablas_id', 'is_read', 'sent_ok', 'user_id', 'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'sent_ok' => 'boolean',
        'created_at' => 'datetime',
    ];
}
