<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single CS-assistant exchange (customer question + assistant reply). Raw
 * transcript — short retention (see assistant:prune). No updated_at.
 */
class AssistantConversation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id', 'message', 'reply', 'answered', 'product_slugs', 'model', 'ip_hash', 'created_at',
    ];

    protected $casts = [
        'answered' => 'boolean',
        'product_slugs' => 'array',
        'created_at' => 'datetime',
    ];
}
