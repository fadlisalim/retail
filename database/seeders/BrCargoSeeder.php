<?php

namespace Database\Seeders;

use App\Models\CargoRate;
use App\Models\ShippingProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * BR Cargo — ekspedisi darat / kapal cepat dari Bandung untuk kiriman BERAT
 * (paket proyek: panel + baterai + inverter). Sumber: "Daftar Harga BR BDG
 * 2026". Aturan dari daftar harga:
 *  - tarif per kg dengan MINIMUM kg per tujuan (10–250 kg);
 *  - volumetrik P×L×T ÷ 4000 (Banjarmasin/Balikpapan/Samarinda ÷ 6000);
 *  - kolli di atas 200 kg dikenakan biaya forklift Rp 150.000;
 *  - belum termasuk asuransi; barang cair/pecah belah wajib packing kayu;
 *  - lama kirim mengikuti jadwal kapal.
 * Kebijakan toko: hanya ditawarkan untuk kiriman ≥ 50 kg. Idempotent.
 */
class BrCargoSeeder extends Seeder
{
    public const PROVIDER_CODE = 'BR';

    public function run(): void
    {
        $provider = ShippingProvider::updateOrCreate(['code' => self::PROVIDER_CODE], [
            'name' => 'BR Cargo',
            'driver' => 'cargo_table',
            'is_active' => true,
            'sort_order' => 6,
            'config' => [
                'origin' => 'BANDUNG',
                'min_shipment_grams' => 50000,   // kebijakan toko: khusus kiriman berat
                'forklift_over_grams' => 200000, // per kolli
                'forklift_fee' => 150000,
                'note' => 'Jadwal mengikuti keberangkatan kapal. Belum termasuk asuransi; barang cair/pecah belah wajib packing kayu.',
            ],
        ]);

        $provider->services()->updateOrCreate(['code' => 'DARAT'], [
            'name' => 'Darat / Kapal Cepat', 'type' => 'cargo', 'volumetric_divisor' => 4000,
            'min_weight_grams' => 50000, 'max_weight_grams' => null,
            'estimated_days' => 'Mengikuti jadwal kapal', 'is_active' => true,
        ]);

        $rows = require database_path('data/br_cargo_bandung.php');
        $now = now();
        $records = [];
        foreach ($rows as [$destination, $minKg, $pricePerKg, $divisor]) {
            $records[] = [
                'provider_code' => self::PROVIDER_CODE, 'origin' => 'BANDUNG',
                'destination' => CargoRate::normalize($destination),
                'min_kg' => $minKg, 'price_per_kg' => $pricePerKg, 'volumetric_divisor' => $divisor,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        // Impor ulang bersih (idempotent): tarif lama asal Bandung dihapus dulu.
        CargoRate::where('provider_code', self::PROVIDER_CODE)->where('origin', 'BANDUNG')->delete();
        foreach (array_chunk($records, 200) as $chunk) {
            DB::table('cargo_rates')->insert($chunk);
        }

        $this->command?->info('BR Cargo: '.count($records).' tujuan diimpor (kiriman ≥ 50 kg).');
    }
}
