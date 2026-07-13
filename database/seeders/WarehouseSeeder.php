<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::updateOrCreate(['code' => 'WH-JKT'], [
            'name' => 'Gudang Jakarta', 'city' => 'Jakarta',
            'address' => 'Jl. Energi Surya No. 1, Jakarta Selatan',
            'is_default' => true, 'is_active' => true,
        ]);

        Warehouse::updateOrCreate(['code' => 'WH-SBY'], [
            'name' => 'Gudang Surabaya', 'city' => 'Surabaya',
            'address' => 'Jl. Industri No. 45, Surabaya',
            'is_default' => false, 'is_active' => true,
        ]);
    }
}
