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
        // Retire the demo couriers that shipped with placeholder tariffs. Real
        // shipping is handled by Indah Cargo (see IndahCargoSeeder); deleting the
        // provider cascades to its services and rates. Re-running this seeder on an
        // existing install therefore cleans them out.
        ShippingProvider::whereIn('code', ['JNE', 'SICEPAT'])->delete();

        ShippingSetting::updateOrCreate(['id' => 1], [
            // For Indah Cargo, packing_fee is the wooden-crate rate PER KG (× billable kg),
            // charged only for items weighing at least packing_min_item_grams each.
            'packing_fee' => 5000,
            'packing_min_item_grams' => 5000,
            'handling_fee' => 0,
            'insurance_percent' => 0,
            'free_shipping_min_subtotal' => null,
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
