<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingProvider extends Model
{
    protected $fillable = ['code', 'name', 'driver', 'is_active', 'config', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(ShippingService::class);
    }
}
