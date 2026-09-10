<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tarif kargo per tujuan untuk ekspedisi dengan daftar harga statis (driver
 * 'cargo_table'): minimum kg, harga per kg, pembagi volumetrik per tujuan.
 */
class CargoRate extends Model
{
    protected $fillable = ['provider_code', 'origin', 'destination', 'min_kg', 'price_per_kg', 'volumetric_divisor'];

    protected $casts = ['price_per_kg' => 'decimal:2'];

    /** Samakan gaya dengan tabel Indah: huruf besar tanpa awalan Kota/Kab. */
    public static function normalize(?string $name): string
    {
        $name = Str::upper(trim((string) $name));
        $name = (string) preg_replace('/^(KOTA|KABUPATEN|KAB\.?|KOTAMADYA)\s+/', '', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Cari tarif untuk salah satu nama tujuan (kota dulu, lalu kecamatan —
     * banyak tujuan di daftar BR setingkat kecamatan, mis. "Bati Bati").
     * Cocok persis dulu, baru awalan/mengandung.
     */
    public static function lookup(string $providerCode, ?string ...$candidates): ?self
    {
        foreach ($candidates as $candidate) {
            $needle = self::normalize($candidate);
            if ($needle === '') {
                continue;
            }

            $base = static::where('provider_code', $providerCode);
            $hit = (clone $base)->where('destination', $needle)->first()
                ?? (clone $base)->where('destination', 'like', $needle.'%')->orderByRaw('LENGTH(destination)')->first()
                ?? (clone $base)->where('destination', 'like', '%'.$needle.'%')->orderByRaw('LENGTH(destination)')->first();
            if ($hit) {
                return $hit;
            }
        }

        return null;
    }
}
