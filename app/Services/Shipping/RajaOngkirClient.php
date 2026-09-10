<?php

namespace App\Services\Shipping;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien API ongkir RajaOngkir (dikelola Komerce) — tarif kurir reguler
 * (JNE, J&T, SiCepat, POS, TIKI, AnterAja, …) dari gudang Bandung ke
 * kelurahan tujuan, dihitung real-time saat checkout.
 *
 * Paket gratis dibatasi ±100 request/hari, jadi SEMUA hasil di-cache:
 * pencarian tujuan 7 hari, tarif (per tujuan × berat kg × kurir) selama
 * `cache_minutes`, dan kegagalan 10 menit supaya API yang sedang down tidak
 * dihajar tiap orang buka checkout. API key hanya dari .env.
 */
class RajaOngkirClient
{
    private const FAILURE_TTL_MINUTES = 10;

    /** Nama pendek kurir untuk label opsi checkout (API mengembalikan nama panjang). */
    private const COURIER_NAMES = [
        'jne' => 'JNE', 'jnt' => 'J&T Express', 'sicepat' => 'SiCepat', 'pos' => 'POS Indonesia',
        'tiki' => 'TIKI', 'anteraja' => 'AnterAja', 'ninja' => 'Ninja Xpress', 'lion' => 'Lion Parcel',
        'ide' => 'ID Express', 'sap' => 'SAP Express', 'wahana' => 'Wahana', 'rex' => 'REX',
        'rpx' => 'RPX', 'ncs' => 'NCS', 'sentral' => 'Sentral Cargo', 'star' => 'Star Cargo', 'dse' => 'DSE',
    ];

    /**
     * Layanan yang tidak relevan untuk paket toko: dokumen, barang berbahaya/
     * berharga, super speed (ratusan ribu), dan tier trucking "JTR<130" dsb.
     */
    private const EXCLUDED_SERVICE_PATTERNS = [
        '/\bDOK\b/i', '/DOCUMENT/i', '/DOKUMEN/i', '/DANGEROUS/i', '/VALUABLE/i',
        '/^SPS$/i', '/SUPER SPEED/i', '/[<>]/',
    ];

    /** Layanan kargo per kg dengan minimum 10 kg — hanya masuk akal untuk paket berat. */
    private const CARGO_SERVICE_CODES = ['JTR', 'GOKIL'];

    private const CARGO_MIN_GRAMS = 10000;

    private const MAX_PER_COURIER = 2;

    private const MAX_TOTAL = 8;

    public ?string $lastError = null;

    public function enabled(): bool
    {
        return (bool) config('services.rajaongkir.enabled')
            && (string) config('services.rajaongkir.api_key') !== ''
            && $this->originId() !== null;
    }

    public function originId(): ?int
    {
        $origin = (int) config('services.rajaongkir.origin_id');

        return $origin > 0 ? $origin : null;
    }

    public function couriers(): string
    {
        return trim((string) config('services.rajaongkir.couriers', 'jne:jnt:sicepat'));
    }

    public function maxWeightGrams(): int
    {
        return max(1000, (int) config('services.rajaongkir.max_weight_grams', 50000));
    }

    public static function courierName(string $code, ?string $fallback = null): string
    {
        return self::COURIER_NAMES[strtolower($code)] ?? ($fallback ?: strtoupper($code));
    }

    /**
     * Cari kelurahan/kecamatan tujuan berdasarkan kata kunci.
     *
     * @return list<array{id:int,label:string,province:string,city:string,district:string,subdistrict:string,postal_code:string}>
     */
    public function searchDestination(string $keyword, int $limit = 10): array
    {
        $keyword = trim((string) preg_replace('/\s+/', ' ', $keyword));
        if (mb_strlen($keyword) < 3) {
            return [];
        }

        $key = 'rajaongkir:dest:'.md5(mb_strtolower($keyword).'|'.$limit);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->request('get', '/destination/domestic-destination', [
            'search' => $keyword, 'limit' => $limit, 'offset' => 0,
        ]);

        $rows = [];
        foreach ($this->dataRows($response) as $row) {
            if (! isset($row['id'])) {
                continue;
            }
            $rows[] = [
                'id' => (int) $row['id'],
                'label' => (string) ($row['label'] ?? implode(', ', array_filter([
                    $row['subdistrict_name'] ?? null, $row['district_name'] ?? null, $row['city_name'] ?? null, $row['province_name'] ?? null, $row['zip_code'] ?? null,
                ]))),
                'province' => (string) ($row['province_name'] ?? ''),
                'city' => (string) ($row['city_name'] ?? ''),
                'district' => (string) ($row['district_name'] ?? ''),
                'subdistrict' => (string) ($row['subdistrict_name'] ?? ''),
                'postal_code' => (string) ($row['zip_code'] ?? ''),
            ];
        }

        Cache::put($key, $rows, $response ? now()->addDays(7) : now()->addMinutes(self::FAILURE_TTL_MINUTES));

        return $rows;
    }

    /**
     * Tarif semua layanan kurir ke satu tujuan. Berat dibulatkan ke atas per kg
     * (kurir menagih per kg) — sekaligus memperbesar peluang cache kena.
     *
     * @return list<array{courier:string,courier_name:string,service:string,description:string,cost:float,etd:string}>
     */
    public function domesticCost(int $destinationId, int $weightGrams, ?string $couriers = null): array
    {
        $origin = $this->originId();
        if (! $origin || $destinationId <= 0) {
            return [];
        }

        $couriers = $couriers ?: $this->couriers();
        $weight = max(1, (int) ceil($weightGrams / 1000)) * 1000;

        $key = "rajaongkir:cost:{$origin}:{$destinationId}:{$weight}:".md5($couriers);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->request('post', '/calculate/domestic-cost', [
            'origin' => $origin, 'destination' => $destinationId, 'weight' => $weight,
            'courier' => $couriers, 'price' => 'lowest',
        ]);

        $rows = [];
        foreach ($this->dataRows($response) as $row) {
            // Format Komerce: satu baris per layanan. Format RajaOngkir lama:
            // per kurir dengan daftar `costs` — diratakan supaya sama.
            if (isset($row['costs']) && is_array($row['costs'])) {
                foreach ($row['costs'] as $svc) {
                    $first = $svc['cost'][0] ?? [];
                    $rows[] = $this->costRow($row['code'] ?? '', $row['name'] ?? '', $svc['service'] ?? '', $svc['description'] ?? '', $first['value'] ?? 0, $first['etd'] ?? '');
                }

                continue;
            }
            $rows[] = $this->costRow($row['code'] ?? '', $row['name'] ?? '', $row['service'] ?? '', $row['description'] ?? '', $row['cost'] ?? 0, $row['etd'] ?? '');
        }
        $rows = array_values(array_filter($rows, fn ($r) => $r['courier'] !== '' && $r['service'] !== '' && $r['cost'] > 0));
        usort($rows, fn ($a, $b) => $a['cost'] <=> $b['cost']);

        $ttl = $response ? now()->addMinutes(max(5, (int) config('services.rajaongkir.cache_minutes', 720))) : now()->addMinutes(self::FAILURE_TTL_MINUTES);
        Cache::put($key, $rows, $ttl);

        return $rows;
    }

    /**
     * Saring daftar tarif mentah jadi pilihan yang layak tampil di checkout:
     * buang layanan non-paket, layanan kargo hanya untuk ≥ 10 kg, maksimal
     * 2 layanan termurah per kurir, total dibatasi, tetap urut termurah.
     *
     * @param  list<array{courier:string,courier_name:string,service:string,description:string,cost:float,etd:string}>  $rows
     * @return list<array{courier:string,courier_name:string,service:string,description:string,cost:float,etd:string}>
     */
    public function curate(array $rows, int $weightGrams): array
    {
        $perCourier = [];
        $kept = [];

        foreach ($rows as $row) {
            $haystack = $row['service'].' '.$row['description'];
            foreach (self::EXCLUDED_SERVICE_PATTERNS as $pattern) {
                if (preg_match($pattern, $haystack)) {
                    continue 2;
                }
            }
            if (in_array($row['service'], self::CARGO_SERVICE_CODES, true) && $weightGrams < self::CARGO_MIN_GRAMS) {
                continue;
            }
            if (($perCourier[$row['courier']] ?? 0) >= self::MAX_PER_COURIER) {
                continue;
            }

            // Keterangan yang cuma angka/kode internal (POS "240") tidak informatif.
            if (preg_match('/^[\d\s.-]*$/', $row['description']) || strcasecmp($row['description'], $row['service']) === 0) {
                $row['description'] = '';
            }

            $perCourier[$row['courier']] = ($perCourier[$row['courier']] ?? 0) + 1;
            $kept[] = $row;
        }

        return array_slice($kept, 0, self::MAX_TOTAL);
    }

    /** Jumlah panggilan API hari ini (pantau kuota paket gratis). */
    public function callsToday(): int
    {
        return (int) Cache::get($this->counterKey(), 0);
    }

    private function costRow(string $code, string $name, string $service, string $description, mixed $cost, mixed $etd): array
    {
        return [
            'courier' => strtolower(trim($code)),
            'courier_name' => self::courierName($code, $name),
            'service' => strtoupper(trim((string) $service)),
            'description' => trim((string) $description),
            'cost' => (float) $cost,
            'etd' => trim((string) $etd),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function dataRows(?Response $response): array
    {
        if (! $response) {
            return [];
        }
        $data = $response->json('data');
        if (! is_array($data)) {
            return [];
        }
        // Sebagian endpoint membungkus lagi: data.results / data.data.
        if (isset($data['results']) && is_array($data['results'])) {
            $data = $data['results'];
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /** Null bila gagal (HTTP error / exception); lastError diisi untuk diagnosa. */
    private function request(string $method, string $path, array $params): ?Response
    {
        $this->lastError = null;
        $this->bumpCounter();

        $url = rtrim((string) config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1'), '/').$path;

        try {
            $pending = Http::withHeaders(['key' => (string) config('services.rajaongkir.api_key'), 'Accept' => 'application/json'])
                ->timeout(12);
            $response = $method === 'get' ? $pending->get($url, $params) : $pending->asForm()->post($url, $params);

            if (! $response->successful()) {
                $this->lastError = 'HTTP '.$response->status().': '.mb_strimwidth((string) ($response->json('meta.message') ?? $response->body()), 0, 300, '…');
                Log::warning('RajaOngkir: permintaan gagal', ['path' => $path, 'status' => $response->status(), 'body' => mb_strimwidth($response->body(), 0, 500, '…')]);

                return null;
            }

            return $response;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('RajaOngkir: tidak bisa dihubungi', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function bumpCounter(): void
    {
        $key = $this->counterKey();
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }

    private function counterKey(): string
    {
        return 'rajaongkir:calls:'.now()->toDateString();
    }
}
