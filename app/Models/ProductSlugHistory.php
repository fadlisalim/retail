<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSlugHistory extends Model
{
    protected $fillable = ['product_id', 'old_slug'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
