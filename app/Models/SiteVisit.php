<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One visit (browser session) with its first-touch traffic source. */
class SiteVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_token', 'source', 'medium', 'campaign', 'content',
        'referrer_host', 'landing_path', 'is_mobile', 'page_views',
        'user_id', 'ip_hash', 'created_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_mobile' => 'boolean',
            'created_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
