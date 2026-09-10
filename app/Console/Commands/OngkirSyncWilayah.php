<?php

namespace App\Console\Commands;

use App\Models\Region;
use App\Services\Shipping\CourierRegions;
use App\Services\Shipping\RajaOngkirClient;
use Illuminate\Console\Command;

/**
 * Isi tabel regions dari RajaOngkir: provinsi + semua kota (±39 request),
 * lalu opsional kecamatan/kelurahan sebagian per hari agar kuota gratis
 * (±100/hari) tidak habis. Sisanya diambil otomatis saat pelanggan memilih
 * wilayah di form alamat.
 */
class OngkirSyncWilayah extends Command
{
    protected $signature = 'ongkir:sync-wilayah {--kecamatan=0 : Ambil kecamatan untuk N kota yang belum tersinkron} {--kelurahan=0 : Ambil kelurahan untuk N kecamatan yang belum tersinkron}';

    protected $description = 'Sinkronkan wilayah (provinsi/kota/kecamatan/kelurahan) dari RajaOngkir ke tabel regions';

    public function handle(CourierRegions $regions, RajaOngkirClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('Integrasi RajaOngkir belum aktif (cek RAJAONGKIR_* di .env).');

            return self::FAILURE;
        }

        $before = $client->callsToday();

        $provinces = $regions->provinces();
        if ($provinces->isEmpty()) {
            $this->error('Gagal mengambil provinsi: '.($client->lastError ?? 'tidak ada data'));

            return self::FAILURE;
        }
        $this->info($provinces->count().' provinsi.');

        $cities = 0;
        foreach ($provinces as $province) {
            $client->lastError = null;
            $cities += $regions->children($province)->count();
            if ($client->lastError) {
                $this->warn('Kota untuk '.$province->name.': '.$client->lastError);
            }
        }
        $this->info($cities.' kota/kabupaten.');

        foreach ([['kecamatan', 'city'], ['kelurahan', 'district']] as [$option, $type]) {
            $limit = (int) $this->option($option);
            if ($limit <= 0) {
                continue;
            }
            $done = 0;
            $failures = 0;
            foreach (Region::where('type', $type)->whereNull('children_synced_at')->orderBy('name')->limit($limit)->get() as $parent) {
                $client->lastError = null;
                $n = $regions->children($parent)->count();
                if ($client->lastError) {
                    $this->warn($parent->name.': '.$client->lastError);
                    // Tiga kegagalan beruntun = API/kuota bermasalah — jangan buang request lagi.
                    if (++$failures >= 3) {
                        $this->error('Berhenti: 3 kegagalan beruntun.');
                        break;
                    }

                    continue;
                }
                $failures = 0;
                $done++;
                $this->line("  {$parent->name}: {$n} {$option}");
            }
            $sisa = Region::where('type', $type)->whereNull('children_synced_at')->count();
            $this->info("{$option}: {$done} induk disinkron, {$sisa} induk belum.");
        }

        $this->line('Request API dipakai: '.($client->callsToday() - $before).' (total hari ini '.$client->callsToday().').');

        return self::SUCCESS;
    }
}
