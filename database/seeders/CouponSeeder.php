<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::updateOrCreate(['code' => 'HEMAT10'], [
            'name' => 'Diskon 10%', 'type' => 'percent', 'value' => 10,
            'min_subtotal' => 1000000, 'max_discount' => 500000,
            'usage_limit' => 100, 'usage_limit_per_user' => 1, 'is_active' => true,
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addMonth(),
        ]);

        Coupon::updateOrCreate(['code' => 'SURYA50K'], [
            'name' => 'Potongan Rp50.000', 'type' => 'fixed', 'value' => 50000,
            'min_subtotal' => 500000, 'is_active' => true,
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addMonth(),
        ]);

        Coupon::updateOrCreate(['code' => 'GRATISONGKIR'], [
            'name' => 'Gratis Ongkir', 'type' => 'free_shipping', 'value' => 0,
            'min_subtotal' => 2000000, 'is_active' => true,
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addMonth(),
        ]);
    }
}
