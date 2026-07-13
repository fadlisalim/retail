<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone', 'company_name', 'npwp',
        'province', 'city', 'district', 'subdistrict', 'postal_code', 'region_id',
        'address_line', 'landmark', 'latitude', 'longitude', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function fullAddress(): string
    {
        return collect([
            $this->address_line,
            $this->subdistrict,
            $this->district,
            $this->city,
            $this->province,
            $this->postal_code,
        ])->filter()->implode(', ');
    }
}
