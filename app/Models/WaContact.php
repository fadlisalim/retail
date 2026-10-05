<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kontak WhatsApp untuk campaign promo + status izin (consent) menerima promo. */
class WaContact extends Model
{
    public const CONSENT_UNKNOWN = 'unknown';

    public const CONSENT_IN = 'opted_in';

    public const CONSENT_OUT = 'opted_out';

    protected $fillable = [
        'phone', 'name', 'user_id', 'source', 'tags', 'interests',
        'consent_status', 'consent_source', 'consent_proof', 'consent_at',
        'opted_out_at', 'opted_out_reason', 'last_promo_at',
        'orders_count', 'last_order_at', 'total_spent', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'interests' => 'array',
            'consent_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'last_promo_at' => 'datetime',
            'last_order_at' => 'datetime',
            'total_spent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaCampaignMessage::class, 'contact_id');
    }

    public function isOptedIn(): bool
    {
        return $this->consent_status === self::CONSENT_IN;
    }

    public function isOptedOut(): bool
    {
        return $this->consent_status === self::CONSENT_OUT;
    }

    /** Hanya kontak dengan izin eksplisit yang boleh jadi penerima promo. */
    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('consent_status', self::CONSENT_IN);
    }

    public function consentLabel(): string
    {
        return match ($this->consent_status) {
            self::CONSENT_IN => 'Izin promo',
            self::CONSENT_OUT => 'Berhenti (STOP)',
            default => 'Belum ada izin',
        };
    }

    /** Nama panggilan untuk personalisasi: kata pertama, atau "Kak" bila kosong. */
    public function firstName(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return 'Kak';
        }
        $first = explode(' ', $name)[0];

        return mb_strlen($first) <= 2 ? $name : $first;
    }
}
