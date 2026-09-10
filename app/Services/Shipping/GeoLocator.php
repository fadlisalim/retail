<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Koordinat GPS → nama wilayah (provinsi, kota, kecamatan, kelurahan) lewat
 * reverse geocoding OpenStreetMap Nominatim. Hasil di-cache per ±100 m
 * (3 desimal) selama 30 hari; gagal → null (pelanggan diminta pilih manual).
 */
class GeoLocator
{
    /** @return array{province:?string,city:?string,district:?string,subdistrict:?string,postal_code:?string,label:string}|null */
    public function reverse(float $lat, float $lng): ?array
    {
        if ($lat < -11.5 || $lat > 6.5 || $lng < 94 || $lng > 141.5) {
            return null; // di luar Indonesia
        }

        $key = sprintf('geo:reverse:%.3f:%.3f', $lat, $lng);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.nominatim.user_agent', 'EnergiClick/1.0 (+https://energi.click)'),
                'Accept-Language' => 'id',
            ])->timeout(8)->get(rtrim((string) config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/').'/reverse', [
                'format' => 'jsonv2', 'lat' => $lat, 'lon' => $lng, 'zoom' => 16, 'addressdetails' => 1,
            ]);
        } catch (Throwable $e) {
            Log::warning('Reverse geocode gagal', ['error' => $e->getMessage()]);
            Cache::put($key, false, now()->addMinutes(10));

            return null;
        }

        $address = $response->successful() ? (array) $response->json('address', []) : [];
        if (! $address) {
            Cache::put($key, false, now()->addMinutes(10));

            return null;
        }

        $pick = fn (array $keys) => collect($keys)->map(fn ($k) => $address[$k] ?? null)->first(fn ($v) => is_string($v) && trim($v) !== '');

        $place = [
            'province' => $pick(['state', 'province']),
            'city' => $pick(['city', 'regency', 'county', 'town', 'municipality']),
            'district' => $pick(['city_district', 'district', 'suburb']),
            'subdistrict' => $pick(['village', 'neighbourhood', 'hamlet', 'quarter']),
            'postal_code' => $pick(['postcode']),
        ];
        // Nama admin Indonesia di OSM sering berawalan "Kecamatan X"/"Kelurahan X" — bersihkan.
        foreach (['district', 'subdistrict', 'city'] as $f) {
            $place[$f] = $place[$f] ? trim((string) preg_replace('/^(Kecamatan|Kelurahan|Desa|Kota|Kabupaten)\s+/i', '', $place[$f])) : null;
        }
        $place['label'] = implode(', ', array_filter([$place['subdistrict'], $place['district'], $place['city'], $place['province']]));

        Cache::put($key, $place, now()->addDays(30));

        return $place;
    }
}
