<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductQuestion extends Model
{
    protected $fillable = ['product_id', 'user_id', 'name', 'phone', 'question', 'is_visible'];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    /**
     * Nomor WA untuk tampilan publik: cukup untuk dikenali pemiliknya sendiri,
     * tidak cukup untuk dihubungi orang lain. 6281234567890 → 0812••••••90.
     */
    public function maskedPhone(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);
        if ($digits === '') {
            return null;
        }

        $local = '0'.substr(preg_replace('/^620?/', '', $digits), 0);

        return substr($local, 0, 4).str_repeat('•', max(strlen($local) - 6, 2)).substr($local, -2);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ProductAnswer::class);
    }
}
