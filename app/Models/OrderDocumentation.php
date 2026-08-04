<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One documentation photo attached to an order stage. */
class OrderDocumentation extends Model
{
    protected $fillable = ['order_id', 'stage', 'path', 'caption', 'is_public', 'sort_order', 'created_by'];

    protected $casts = ['is_public' => 'boolean'];

    /** Stages in the order they happen, with the label shown to customers. */
    public const STAGES = [
        'persiapan' => 'Penyiapan Barang',
        'testing' => 'Testing & QC',
        'packing' => 'Packing',
        'pengiriman' => 'Pengiriman',
        'terpasang' => 'Terpasang / Serah Terima',
    ];

    /** Emoji marker per stage — keeps the timeline readable at a glance. */
    public const STAGE_ICONS = [
        'persiapan' => '📦',
        'testing' => '🔌',
        'packing' => '🎁',
        'pengiriman' => '🚚',
        'terpasang' => '✅',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? ucfirst($this->stage);
    }

    public function stageIcon(): string
    {
        return self::STAGE_ICONS[$this->stage] ?? '📷';
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
