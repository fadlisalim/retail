<?php

namespace App\Console\Commands;

use App\Services\Shipping\RajaOngkirClient;
use Illuminate\Console\Command;

/**
 * Cari ID kelurahan RajaOngkir — dipakai untuk menentukan RAJAONGKIR_ORIGIN_ID
 * (kelurahan gudang) dan memeriksa apakah suatu tujuan dikenali API.
 */
class OngkirCari extends Command
{
    protected $signature = 'ongkir:cari {kata : Nama kelurahan/kecamatan, mis. "Coblong"} {--limit=10}';

    protected $description = 'Cari ID kelurahan tujuan/asal di RajaOngkir (Komerce)';

    public function handle(RajaOngkirClient $client): int
    {
        if ((string) config('services.rajaongkir.api_key') === '') {
            $this->error('RAJAONGKIR_API_KEY belum diisi di .env.');

            return self::FAILURE;
        }

        $rows = $client->searchDestination($this->argument('kata'), (int) $this->option('limit'));

        if ($client->lastError) {
            $this->error('API gagal: '.$client->lastError);

            return self::FAILURE;
        }
        if (! $rows) {
            $this->warn('Tidak ada hasil untuk "'.$this->argument('kata').'".');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Kelurahan', 'Kecamatan', 'Kota', 'Provinsi', 'Kode Pos'], array_map(
            fn ($r) => [$r['id'], $r['subdistrict'], $r['district'], $r['city'], $r['province'], $r['postal_code']],
            $rows,
        ));
        $this->line('Pakai ID kelurahan gudang sebagai RAJAONGKIR_ORIGIN_ID di .env. Panggilan API hari ini: '.$client->callsToday().'.');

        return self::SUCCESS;
    }
}
