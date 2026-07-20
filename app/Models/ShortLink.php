<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShortLink extends Model
{
    protected $fillable = ['code', 'url', 'url_hash', 'clicks'];

    /**
     * Get (or create) the short link for a destination URL. Idempotent: the same
     * URL always maps to the same code.
     */
    public static function for(string $url): self
    {
        return static::firstOrCreate(
            ['url_hash' => sha1($url)],
            ['url' => $url, 'code' => static::uniqueCode()],
        );
    }

    /**
     * Get (or create) a referral short link whose code keeps the affiliate code
     * visible: {productCode}-{affiliateCode} → e.g. /s/Ab3xYz-K4m2Qp. Resolves to
     * the product URL with ?ref=CODE so attribution still fires.
     */
    public static function referral(string $productUrl, string $productCode, string $affiliateCode): self
    {
        $url = $productUrl.(str_contains($productUrl, '?') ? '&' : '?').'ref='.$affiliateCode;

        return static::firstOrCreate(
            ['url_hash' => sha1($url)],
            ['url' => $url, 'code' => $productCode.'-'.$affiliateCode],
        );
    }

    /** The short, shareable URL, e.g. https://energi.click/s/Ab3xYz. */
    public function shortUrl(): string
    {
        return url('/s/'.$this->code);
    }

    private static function uniqueCode(): string
    {
        do {
            $code = Str::random(6);
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
