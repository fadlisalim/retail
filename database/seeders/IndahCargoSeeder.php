<?php

namespace Database\Seeders;

use App\Models\IndahCargoRate;
use App\Models\ShippingProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndahCargoSeeder extends Seeder
{
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
