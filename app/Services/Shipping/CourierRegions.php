<?php

namespace App\Services\Shipping;

use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Wilayah RajaOngkir di database sendiri, diisi BERTAHAP: provinsi & kota
 * sekali (±39 request), kecamatan/kelurahan diambil saat pertama kali
 * dibutuhkan (satu request per induk) lalu tersimpan permanen. Dengan kuota
 * ±100 request/hari, tabel terisi mengikuti wilayah yang benar-benar dipakai
 * pelanggan — tanpa harus mengunduh 80.000 kelurahan di muka.
 */
class CourierRegions
{
    private const ENDPOINTS = [
        'province' => '/destination/province',
        'city' => '/destination/city/%s',
        'district' => '/destination/district/%s',
        'subdistrict' => '/destination/sub-district/%s',
    ];

    public function __construct(private readonly RajaOngkirClient $client) {}

    /** @return Collection<int, Region> */
    public function provinces(): Collection
    {
        $existing = Region::where('type', 'province')->orderBy('name')->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $rows = $this->client->fetchRows(self::ENDPOINTS['province']);
        if ($rows === null) {
            return $existing;
        }
        $this->upsert(null, 'province', $rows);

        return Region::where('type', 'province')->orderBy('name')->get();
    }

    /**
     * Anak sebuah wilayah (kota dari provinsi, kecamatan dari kota, kelurahan
     * dari kecamatan). Diambil dari API sekali saja per induk.
     *
     * @return Collection<int, Region>
     */
    public function children(Region $parent): Collection
    {
        $type = $parent->childType();
        if (! $type) {
            return collect();
        }

        if ($parent->children_synced_at === null) {
            $rows = $this->client->fetchRows(sprintf(self::ENDPOINTS[$type], $parent->code));
            if ($rows !== null) {
                $this->upsert($parent, $type, $rows);
                $parent->forceFill(['children_synced_at' => now()])->save();
            }
        }

        return $parent->children()->where('type', $type)->orderBy('name')->get();
    }

    /** Wilayah kelurahan (atau kecamatan) yang dipakai sebagai tujuan kurir. */
    public function find(int $id, string $type): ?Region
    {
        return Region::where('type', $type)->find($id);
    }

    /** @param  list<array<string,mixed>>  $rows */
    private function upsert(?Region $parent, string $type, array $rows): void
    {
        foreach ($rows as $row) {
            $code = $row['id'] ?? null;
            $name = $row['name'] ?? $row[$type.'_name'] ?? $row['province_name'] ?? $row['city_name'] ?? $row['district_name'] ?? $row['subdistrict_name'] ?? null;
            if ($code === null || ! $name) {
                continue;
            }

            Region::updateOrCreate(
                ['type' => $type, 'code' => (string) $code],
                [
                    'parent_id' => $parent?->id,
                    'name' => self::formatName((string) $name),
                    'postal_code' => isset($row['zip_code']) && $row['zip_code'] !== '' ? (string) $row['zip_code'] : null,
                ],
            );
        }
    }

    /** "KAB. BANDUNG BARAT" → "Kab. Bandung Barat"; "DKI JAKARTA" → "DKI Jakarta". */
    public static function formatName(string $name): string
    {
        $name = Str::title(mb_strtolower(trim(preg_replace('/\s+/', ' ', $name))));

        return (string) preg_replace_callback('/\b(Dki|Di|Dka)\b/', fn ($m) => strtoupper($m[1]), $name);
    }
}
