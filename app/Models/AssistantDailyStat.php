<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-day rollup of assistant volume. Non-personal; longer retention. */
class AssistantDailyStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['day', 'messages', 'answered', 'fallbacks', 'sessions'];

    protected $casts = [
        'day' => 'date',
    ];
}
