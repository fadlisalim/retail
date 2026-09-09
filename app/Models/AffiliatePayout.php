<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePayout extends Model
{
    protected $fillable = [
        'affiliate_id', 'amount', 'status', 'method',
        'bank_name', 'bank_account_number', 'bank_account_holder',
        'reference', 'note', 'processed_by', 'requested_at', 'confirmed_at', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PayoutStatus::class,
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
