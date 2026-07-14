<?php

namespace Database\Seeders;

use App\Models\IndahCargoRate;
use App\Models\ShippingProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndahCargoSeeder extends Seeder
{
    /** Maps Indah Cargo's 52 destination area-groups to the real Indonesian province. */
    private const AREA_PROVINCE = [
        'BALI' => 'Bali',
        'BANDA ACEH' => 'Aceh',
        'BANDAR LAMPUNG' => 'Lampung',
        'BANDUNG' => 'Jawa Barat',
        'BANTEN' => 'Banten',
        'BANYUWANGI' => 'Jawa Timur',
        'BATAM' => 'Kepulauan Riau',
        'BEKASI' => 'Jawa Barat',
        'BOGOR AREA' => 'Jawa Barat',
        'BOJONEGORO' => 'Jawa Timur',
        'CIREBON' => 'Jawa Barat',
        'GRESIK' => 'Jawa Timur',
        'IRIAN JAYA TOL LAUT' => 'Papua',
        'JAKARTA' => 'DKI Jakarta',
        'JAMBI' => 'Jambi',
        'JEMBER' => 'Jawa Timur',
        'JOMBANG' => 'Jawa Timur',
        'KALIMANTAN BARAT TOL LAUT' => 'Kalimantan Barat',
        'KALIMANTAN SELATAN TOL LAUT' => 'Kalimantan Selatan',
        'KALIMANTAN TENGAH TOL LAUT' => 'Kalimantan Tengah',
        'KALIMANTAN TIMUR TOL LAUT' => 'Kalimantan Timur',
        'KARAWANG' => 'Jawa Barat',
        'KEBUMEN' => 'Jawa Tengah',
        'KEDIRI' => 'Jawa Timur',
        'KUDUS' => 'Jawa Tengah',
        'MADIUN' => 'Jawa Timur',
        'MAGELANG' => 'Jawa Tengah',
        'MALANG' => 'Jawa Timur',
        'MALUKU TOL LAUT' => 'Maluku',
        'MALUKU UTARA TOL LAUT' => 'Maluku Utara',
        'NUSA TENGGARA BARAT' => 'Nusa Tenggara Barat',
        'NUSA TENGGARA TIMUR TOL LAUT' => 'Nusa Tenggara Timur',
        'PANGKAL PINANG & BANGKA BELITUNG' => 'Kepulauan Bangka Belitung',
        'PEKALONGAN' => 'Jawa Tengah',
        'PROBOLINGGO' => 'Jawa Timur',
        'PURWOKERTO' => 'Jawa Tengah',
        'RIAU DARATAN' => 'Riau',
        'SEMARANG' => 'Jawa Tengah',
        'SIDOARJO' => 'Jawa Timur',
        'SOLO' => 'Jawa Tengah',
        'SULAWESI BARAT TOL LAUT' => 'Sulawesi Barat',
        'SULAWESI SELATAN TOL LAUT' => 'Sulawesi Selatan',
        'SULAWESI TENGAH TOL LAUT' => 'Sulawesi Tengah',
        'SULAWESI TENGGARA TOL LAUT' => 'Sulawesi Tenggara',
        'SULAWESI UTARA TOL LAUT' => 'Sulawesi Utara',
        'SUMATERA BARAT' => 'Sumatera Barat',
        'SUMATERA SELATAN' => 'Sumatera Selatan',
        'SUMATERA UTARA' => 'Sumatera Utara',
        'SURABAYA' => 'Jawa Timur',
        'TEGAL' => 'Jawa Tengah',
        'TUBAN' => 'Jawa Timur',
        'YOGYAKARTA' => 'DI Yogyakarta',
    ];

    public function run(): void
    {
        // --- Provider + services (driver 'indah' is handled specially by ShippingService) ---
        $indah = ShippingProvider::updateOrCreate(['code' => 'INDAH'], [
            'name' => 'Indah Cargo', 'driver' => 'indah', 'is_active' => true, 'sort_order' => 3,
        ]);

        $indah->services()->updateOrCreate(['code' => 'UDARA'], [
            'name' => 'Via Udara (Prioritas)', 'type' => 'regular', 'volumetric_divisor' => 6000,
            'min_weight_grams' => 1000, 'max_weight_grams' => null, 'estimated_days' => '1-3 hari', 'is_active' => true,
        ]);
        $indah->services()->updateOrCreate(['code' => 'DARAT'], [
            'name' => 'Via Darat / Laut', 'type' => 'cargo', 'volumetric_divisor' => 4000,
            'min_weight_grams' => 10000, 'max_weight_grams' => null, 'estimated_days' => '3-9 hari', 'is_active' => true,
        ]);

        // --- Tariff table (origin Bandung), imported from the committed data file ---
        $rows = require database_path('data/indah_cargo_bandung.php');

        $now = now();
        $records = [];
        foreach ($rows as [$city, $code, $group, $air, $land]) {
            $records[] = [
                'origin' => 'BANDUNG',
                'destination_city' => $city,
                'destination_code' => $code,
                'province_group' => $group,
                // Real province so checkout can cascade province -> city.
                'province' => self::AREA_PROVINCE[$group] ?? $group,
                'air_per_kg' => $air,
                'land_per_kg' => $land,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Fresh import (idempotent): clear the Bandung origin then bulk insert.
        IndahCargoRate::where('origin', 'BANDUNG')->delete();
        foreach (array_chunk($records, 200) as $chunk) {
            DB::table('indah_cargo_rates')->insert($chunk);
        }
    }
}
