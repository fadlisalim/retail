<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    protected $fillable = [
        'order_id', 'type', 'recipient_name', 'phone', 'company_name', 'npwp',
        'province', 'city', 'district', 'subdistrict', 'postal_code',
        'address_line', 'landmark',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fullAddress(): string
    {
        return collect([
            $this->address_line, $this->subdistrict, $this->district,
            $this->city, $this->province, $this->postal_code,
        ])->filter()->implode(', ');
    }
}
