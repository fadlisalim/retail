<?php

namespace App\Console\Commands;

use App\Services\Shipping\RajaOngkirClient;
use Illuminate\Console\Command;

/**
 * Cek tarif kurir reguler dari gudang ke satu ID kelurahan tujuan — untuk
 * memastikan API key, origin, dan daftar kurir bekerja di server produksi.
 */
class OngkirCek extends Command
{
    protected $signature = 'ongkir:cek {tujuan_id : ID kelurahan tujuan (lihat ongkir:cari)} {--berat=1000 : Berat dalam gram} {--kurir= : Daftar kurir, mis. jne:jnt}';

    protected $description = 'Cek tarif kurir RajaOngkir dari gudang ke satu tujuan';

    public function handle(RajaOngkirClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('Integrasi belum aktif: pastikan RAJAONGKIR_ENABLED=true, RAJAONGKIR_API_KEY dan RAJAONGKIR_ORIGIN_ID terisi di .env (lalu php artisan optimize:clear).');

            return self::FAILURE;
        }

        $rows = $client->domesticCost((int) $this->argument('tujuan_id'), (int) $this->option('berat'), $this->option('kurir') ?: null);

        if ($client->lastError) {
            $this->error('API gagal: '.$client->lastError);

            return self::FAILURE;
        }
        if (! $rows) {
            $this->warn('Tidak ada tarif (tujuan tidak dilayani, atau kurir di RAJAONGKIR_COURIERS tidak termasuk paket API Anda).');

            return self::SUCCESS;
        }

        $this->info('Asal ID '.$client->originId().' → tujuan ID '.$this->argument('tujuan_id').', berat '.$this->option('berat').' g, kurir '.($this->option('kurir') ?: $client->couriers()));
        $this->table(['Kurir', 'Layanan', 'Keterangan', 'Tarif', 'Estimasi'], array_map(
            fn ($r) => [$r['courier_name'], $r['service'], $r['description'], 'Rp '.number_format($r['cost'], 0, ',', '.'), $r['etd']],
            $rows,
        ));
        $this->line('Panggilan API hari ini: '.$client->callsToday().' (hasil ini di-cache '.(int) config('services.rajaongkir.cache_minutes').' menit).');

        return self::SUCCESS;
    }
}
