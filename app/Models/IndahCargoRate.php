<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IndahCargoRate extends Model
{
    protected $fillable = [
        'origin', 'destination_city', 'destination_code', 'province_group', 'province', 'air_per_kg', 'land_per_kg',
    ];

    protected $casts = [
        'air_per_kg' => 'decimal:2',
        'land_per_kg' => 'decimal:2',
    ];

    /**
     * Province => [cities] map from the tariff table. Cities are Title-cased for
     * display; the rate lookup upper-cases again. Used by the address & checkout
     * pickers so destinations always match a real Indah Cargo tariff.
     */
    public static function citiesByProvince(): array
    {
        return static::query()
            ->select('province', 'destination_city')
            ->orderBy('province')->orderBy('destination_city')
            ->get()
            ->groupBy('province')
            ->map(fn ($rows) => $rows->pluck('destination_city')
                ->map(fn ($c) => Str::title(mb_strtolower($c)))->unique()->values()->all())
            ->sortKeys()
            ->all();
    }

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
