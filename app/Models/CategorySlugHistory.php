<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySlugHistory extends Model
{
    protected $fillable = ['category_id', 'old_slug'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
