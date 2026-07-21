<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'description', 'image_desktop_path', 'image_mobile_path',
        'button_text', 'button_url', 'position', 'brand_id', 'category_id', 'span', 'is_portrait', 'is_active', 'sort_order',
        'starts_at', 'ends_at',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'is_portrait' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Tailwind column-span class for this banner's width inside a 6-column grid. */
    public function spanClass(): string
    {
        return match ($this->span) {
            'full' => 'sm:col-span-6',
            'half' => 'sm:col-span-3',
            default => 'sm:col-span-2', // third
        };
    }

    /** Normalised YouTube embed URL from button_url (watch / youtu.be / shorts / embed). */
    public function youtubeEmbedUrl(): ?string
    {
        if (! $this->button_url) {
            return null;
        }

        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/))([\w-]{11})~', $this->button_url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        return null;
    }
}
