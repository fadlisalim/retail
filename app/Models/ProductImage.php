<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'video_path', 'alt', 'sort_order', 'watermarked_at'];

    protected $casts = ['watermarked_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** True when this gallery item is a video (path then holds the poster image). */
    public function isVideo(): bool
    {
        return (bool) $this->video_path;
    }

    /** Poster / image URL (used in the thumbnail strip for both photos and videos). */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function videoUrl(): ?string
    {
        return $this->video_path ? asset('storage/'.$this->video_path) : null;
    }
}
