<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A single WhatsApp message (in/out) in the admin inbox. Grouped by phone. */
class WaMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'phone', 'name', 'direction', 'message', 'media_url', 'media_type', 'media_name',
        'wablas_id', 'is_read', 'sent_ok', 'user_id', 'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'sent_ok' => 'boolean',
        'created_at' => 'datetime',
    ];

    /** Renderable inline (thumbnail) rather than as a download link. */
    public function isImage(): bool
    {
        return $this->media_url && $this->media_type === 'image';
    }
}
