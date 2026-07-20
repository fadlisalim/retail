<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-day rollup of what customers asked about: keywords and recommended
 * products. Non-personal (aggregate counts only); longer retention.
 */
class AssistantDailyTerm extends Model
{
    public $timestamps = false;

    protected $fillable = ['day', 'type', 'term', 'label', 'count'];

    protected $casts = [
        'day' => 'date',
    ];
}
