<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IndahCargoRate extends Model
{
    protected $fillable = [
        'origin', 'destination_city', 'destination_code', 'province_group', 'air_per_kg', 'land_per_kg',
    ];

    protected $casts = [
        'air_per_kg' => 'decimal:2',
        'land_per_kg' => 'decimal:2',
    ];

    /** Normalise a free-text city name to match the tariff table. */
    public static function normalizeCity(string $city): string
    {
        $city = Str::upper(trim($city));
        // Drop administrative prefixes so "Kota Bandung" / "Kab. Bogor" match.
        $city = preg_replace('/^(KOTA|KABUPATEN|KAB\.?|KOTAMADYA)\s+/', '', $city);
        $city = preg_replace('/\s+/', ' ', $city);

        return trim($city);
    }

    /**
     * Find the best matching tariff for a destination city (exact, then prefix).
     */
    public static function lookup(string $city): ?self
    {
        $needle = self::normalizeCity($city);
        if ($needle === '') {
            return null;
        }

        $exact = static::where('destination_city', $needle)->first();
        if ($exact) {
            return $exact;
        }

        // Fall back to a prefix / contains match (e.g. "BANDUNG TIMUR" -> "BANDUNG").
        return static::where('destination_city', 'like', $needle.'%')
            ->orWhere('destination_city', 'like', '%'.$needle.'%')
            ->orderByRaw('LENGTH(destination_city)')
            ->first();
    }
}
