<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak operasional campaign (jeda otomatis, error gateway, STOP, dsb.). */
class WaCampaignLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['campaign_id', 'level', 'message', 'context', 'created_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WaCampaign::class, 'campaign_id');
    }

    public static function write(?int $campaignId, string $message, array $context = [], string $level = 'info'): self
    {
        return static::create([
            'campaign_id' => $campaignId, 'level' => $level,
            'message' => mb_substr($message, 0, 500), 'context' => $context ?: null, 'created_at' => now(),
        ]);
    }
}
