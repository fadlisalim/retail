<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['Bezvolt', true, 'Brand internal Rekasurya untuk inverter & baterai lithium.'],
            ['Suryagen', true, 'Panel surya monocrystalline efisiensi tinggi.'],
            ['VoltaMax', true, 'Inverter hybrid & on-grid untuk rumah dan industri.'],
            ['LumenCell', true, 'Baterai LiFePO4 & sistem penyimpanan energi.'],
            ['SolarPrime', false, 'Struktur mounting & aksesoris PLTS.'],
            ['GridTech', false, 'Solar charge controller & proteksi.'],
            ['EnerFlow', false, 'Pompa air tenaga surya & PJU.'],
            ['KabelSurya', false, 'Kabel & konektor PLTS bersertifikat.'],
        ];

        foreach ($brands as $i => [$name, $featured, $desc]) {
            Brand::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'description' => $desc,
                'is_featured' => $featured,
                'is_active' => true,
                'sort_order' => $i,
                'meta_title' => "Produk {$name} — Rekasurya Store",
                'meta_description' => $desc,
            ]);
        }
    }
}
