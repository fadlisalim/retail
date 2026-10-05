<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Campaign promo WhatsApp: pesan + audiens + antrean pesan per penerima. */
class WaCampaign extends Model
{
    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const RUNNING = 'running';

    public const PAUSED = 'paused';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draf',
        self::SCHEDULED => 'Terjadwal',
        self::RUNNING => 'Berjalan',
        self::PAUSED => 'Dijeda',
        self::COMPLETED => 'Selesai',
        self::CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'name', 'status', 'message', 'image_path', 'product_id', 'link_url', 'utm_campaign',
        'segment', 'audience_count', 'scheduled_at', 'started_at', 'finished_at',
        'pause_reason', 'consecutive_failures', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'segment' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaCampaignMessage::class, 'campaign_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WaCampaignLog::class, 'campaign_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::SCHEDULED, self::RUNNING, self::PAUSED], true);
    }

    public function canEdit(): bool
    {
        return in_array($this->status, [self::DRAFT, self::SCHEDULED], true) && $this->messages()->doesntExist();
    }

    /** Hitungan per status untuk laporan (nilai nyata dari antrean, bukan perkiraan). */
    public function stats(): array
    {
        $by = $this->messages()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $get = fn (string ...$keys) => array_sum(array_map(fn ($k) => (int) ($by[$k] ?? 0), $keys));

        return [
            'total' => array_sum($by),
            'queued' => $get('queued', 'sending'),
            'accepted' => $get('accepted', 'sent', 'delivered', 'read'), // diterima gateway (bukan = sampai)
            'sent' => $get('sent', 'delivered', 'read'),
            'delivered' => $get('delivered', 'read'),
            'read' => $get('read'),
            'failed' => $get('failed'),
            'uncertain' => $get('uncertain'),
            'skipped' => $get('skipped'),
            'cancelled' => $get('cancelled'),
            'replied' => (int) $this->messages()->where('reply_count', '>', 0)->count(),
            'opted_out' => (int) $this->messages()->whereHas('contact', fn ($q) => $q->where('consent_status', WaContact::CONSENT_OUT))->count(),
        ];
    }
}
