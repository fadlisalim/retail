<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Aggregator for the full BLUETTI catalogue (power stations, expansion battery,
 * and foldable solar panels). Each child seeder is idempotent (firstOrCreate +
 * wasRecentlyCreated stock guard), so this is safe to run on every deploy — it
 * never resets admin-edited stock or data.
 *
 *   php artisan db:seed --class=BluettiPowerSeeder --force
 */
class BluettiPowerSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BluettiAc50pSeeder::class,
            BluettiAc70pSeeder::class,
            BluettiEb3aSeeder::class,
            BluettiPremium30V2Seeder::class,
            BluettiElite100V2Seeder::class,
            BluettiAc180pSeeder::class,
            BluettiElite200V2Seeder::class,
            BluettiAc200plSeeder::class,
            BluettiApex300Seeder::class,
            BluettiB300kSeeder::class,
            BluettiPv100Seeder::class,
            BluettiPv200Seeder::class,
        ]);
    }
}
