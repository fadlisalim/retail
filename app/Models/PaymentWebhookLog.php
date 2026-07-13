<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    protected $fillable = [
        'provider', 'event', 'external_id', 'idempotency_key',
        'signature_valid', 'processed', 'payload', 'ip_address',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'processed' => 'boolean',
    ];
}
