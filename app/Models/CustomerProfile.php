<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'npwp', 'customer_type',
        'term_payment_approved', 'credit_limit', 'referral_source',
    ];

    protected $casts = [
        'term_payment_approved' => 'boolean',
        'credit_limit' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
