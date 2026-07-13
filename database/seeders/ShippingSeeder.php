<?php

namespace Database\Seeders;

use App\Models\ShippingProvider;
use App\Models\ShippingSetting;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        ShippingSetting::updateOrCreate(['id' => 1], [
            'packing_fee' => 5000,
            'handling_fee' => 0,
            'insurance_percent' => 0.2,
            'free_shipping_min_subtotal' => 15000000,
            'default_volumetric_divisor' => 6000,
            'weight_rounding_grams' => 1000,
        ]);

        $jawa = ShippingZone::updateOrCreate(['name' => 'Jawa'], [
            'provinces' => ['DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Banten', 'DI Yogyakarta'],
            'is_active' => true,
        ]);
        $luarJawa = ShippingZone::updateOrCreate(['name' => 'Luar Jawa'], [
            'provinces' => [], // empty = matches any other province
            'is_active' => true,
        ]);

        // Regular courier (volumetric divisor 6000, capped at 30 kg).
        $jne = ShippingProvider::updateOrCreate(['code' => 'JNE'], [
            'name' => 'JNE', 'driver' => 'weight', 'is_active' => true, 'sort_order' => 1,
        ]);
        $jneReg = $jne->services()->updateOrCreate(['code' => 'REG'], [
            'name' => 'Reguler', 'type' => 'regular', 'volumetric_divisor' => 6000,
            'min_weight_grams' => 1000, 'max_weight_grams' => 30000, 'estimated_days' => '2-4 hari', 'is_active' => true,
        ]);
        $jneReg->rates()->updateOrCreate(['shipping_zone_id' => $jawa->id], ['price_per_kg' => 12000, 'min_price' => 12000, 'base_price' => 3000]);
        $jneReg->rates()->updateOrCreate(['shipping_zone_id' => $luarJawa->id], ['price_per_kg' => 25000, 'min_price' => 25000, 'base_price' => 5000]);

        // Cargo courier for heavier shipments.
        $sicepat = ShippingProvider::updateOrCreate(['code' => 'SICEPAT'], [
            'name' => 'SiCepat', 'driver' => 'weight', 'is_active' => true, 'sort_order' => 2,
        ]);
        $cargo = $sicepat->services()->updateOrCreate(['code' => 'GOKIL'], [
            'name' => 'Kargo', 'type' => 'cargo', 'volumetric_divisor' => 4000,
            'min_weight_grams' => 10000, 'max_weight_grams' => null, 'estimated_days' => '3-7 hari', 'is_active' => true,
        ]);
        $cargo->rates()->updateOrCreate(['shipping_zone_id' => $jawa->id], ['price_per_kg' => 3500, 'min_price' => 50000, 'base_price' => 0]);
        $cargo->rates()->updateOrCreate(['shipping_zone_id' => $luarJawa->id], ['price_per_kg' => 6000, 'min_price' => 80000, 'base_price' => 0]);

        // Warehouse pickup (free).
        $pickup = ShippingProvider::updateOrCreate(['code' => 'PICKUP'], [
            'name' => 'Ambil di Gudang', 'driver' => 'pickup', 'is_active' => true, 'sort_order' => 9,
        ]);
        $pickup->services()->updateOrCreate(['code' => 'WAREHOUSE'], [
            'name' => 'Ambil Sendiri', 'type' => 'pickup', 'volumetric_divisor' => 6000,
            'min_weight_grams' => 0, 'estimated_days' => 'Sesuai jadwal', 'is_active' => true,
        ]);
    }
}
