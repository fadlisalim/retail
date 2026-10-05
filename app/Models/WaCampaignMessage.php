<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pesan campaign untuk satu penerima (baris antrean persisten). */
class WaCampaignMessage extends Model
{
    public const QUEUED = 'queued';

    public const SENDING = 'sending';

    public const ACCEPTED = 'accepted';   // diterima gateway Wablas (belum tentu sampai)

    public const SENT = 'sent';           // Wablas melapor terkirim ke WhatsApp

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    public const UNCERTAIN = 'uncertain'; // request timeout / hasil tak diketahui — tidak dikirim ulang otomatis

    public const SKIPPED = 'skipped';     // dilewati saat dispatch (izin dicabut, batas 7 hari, dll.)

    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::QUEUED => 'Antre',
        self::SENDING => 'Mengirim',
        self::ACCEPTED => 'Diterima gateway',
        self::SENT => 'Terkirim',
        self::DELIVERED => 'Sampai (delivered)',
        self::READ => 'Dibaca',
        self::FAILED => 'Gagal',
        self::UNCERTAIN => 'Tidak pasti (timeout)',
        self::SKIPPED => 'Dilewati',
        self::CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'campaign_id', 'contact_id', 'phone', 'status', 'rendered_message', 'wablas_id',
        'attempts', 'next_attempt_at', 'error', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'reply_count',
    ];

    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaCampaign::class, 'campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Urutan status laporan Wablas; webhook yang datang terlambat tidak boleh memundurkan status. */
    public static function rank(string $status): int
    {
        return ['accepted' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4][$status] ?? 0;
    }
}
