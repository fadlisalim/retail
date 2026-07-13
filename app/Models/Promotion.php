<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'name', 'type', 'discount_type', 'value', 'max_discount', 'targets',
        'min_qty', 'is_combinable', 'is_active', 'starts_at', 'ends_at', 'sort_order',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'targets' => 'array',
        'min_qty' => 'decimal:2',
        'is_combinable' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
