<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVideo extends Model
{
    protected $fillable = ['product_id', 'title', 'url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** YouTube embed URL (watch / youtu.be / shorts / embed / live), or null. */
    public function embedUrl(): ?string
    {
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/))([\w-]{11})~', (string) $this->url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        return null;
    }
}
