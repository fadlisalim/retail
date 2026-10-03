<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/** One visit (browser session) with its first-touch traffic source. */
class SiteVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_token', 'source', 'medium', 'campaign', 'content', 'gclid',
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

    /** Baris kunjungan untuk sesi request ini (dipakai saat pesanan / RFQ dibuat). */
    public static function forRequest(Request $request): ?self
    {
        if (! $request->hasSession()) {
            return null;
        }

        return static::where('session_token', hash('sha256', (string) $request->session()->getId()))->first();
    }

    /** Google Click ID dari URL: gclid, atau gbraid/wbraid (iOS tanpa cookie pihak ketiga). */
    public static function clickIdFrom(Request $request): ?string
    {
        foreach (['gclid', 'gbraid', 'wbraid'] as $key) {
            $value = trim((string) $request->query($key, ''));
            if ($value !== '' && preg_match('/^[A-Za-z0-9_-]{1,120}$/', $value)) {
                return $value;
            }
        }

        return null;
    }
}
