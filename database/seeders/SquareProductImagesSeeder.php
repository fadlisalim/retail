<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/** Jalankan products:square-images sekali lewat deploy/seeders.txt. */
class SquareProductImagesSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('products:square-images');
        $this->command?->info(trim(Artisan::output()));
    }
}
