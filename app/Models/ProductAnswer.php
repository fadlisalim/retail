<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnswer extends Model
{
    protected $fillable = ['product_question_id', 'user_id', 'is_staff', 'answer', 'wa_notified_at'];

    protected $casts = [
        'is_staff' => 'boolean',
        'wa_notified_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(ProductQuestion::class, 'product_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
