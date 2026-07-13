<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDocument extends Model
{
    protected $fillable = ['product_id', 'type', 'title', 'path'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
