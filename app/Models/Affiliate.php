<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    protected $fillable = [
        'user_id', 'code', 'status',
        'full_name', 'id_number', 'phone', 'address', 'npwp', 'channel',
        'ktp_photo_path', 'selfie_photo_path',
        'bank_name', 'bank_account_number', 'bank_account_holder',
        'note', 'verified_at', 'verified_by',
    ];

    protected $casts = [
        'status' => AffiliateStatus::class,
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Active;
    }

    public function referralUrl(): string
    {
        return url('/').'?ref='.$this->code;
    }

    /** Total commission that has cleared (order completed). */
    public function approvedTotal(): float
    {
        return (float) $this->commissions()->where('status', CommissionStatus::Approved->value)->sum('amount');
    }

    /** Commission still held pending order completion. */
    public function pendingTotal(): float
    {
        return (float) $this->commissions()->where('status', CommissionStatus::Pending->value)->sum('amount');
    }

    /** Commission that has been paid out. */
    public function paidTotal(): float
    {
        return (float) $this->commissions()->where('status', CommissionStatus::Paid->value)->sum('amount');
    }

    /** Amount tied up in payout requests that aren't rejected yet. */
    public function reservedByPayouts(): float
    {
        return (float) $this->payouts()
            ->where('status', '!=', PayoutStatus::Rejected->value)
            ->sum('amount');
    }

    /** Withdrawable balance: cleared commission minus anything already requested/paid. */
    public function availableBalance(): float
    {
        return round($this->approvedTotal() - $this->reservedByPayouts(), 2);
    }
}
