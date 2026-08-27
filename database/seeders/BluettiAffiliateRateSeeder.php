<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Kebijakan Agustus 2026: fee reseller/afiliator SEMUA produk BLUETTI
 * diseragamkan ke 5%. Menimpa nilai per-produk yang ada (memang itu
 * tujuannya — penyeragaman); produk Bluetti yang ditambahkan setelah ini
 * perlu diisi 5% juga (atau jalankan ulang seeder ini). Idempotent.
 */
class BluettiAffiliateRateSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::where('slug', 'bluetti')->first();
        if (! $brand) {
            $this->command?->warn('Brand bluetti tidak ditemukan — belum ada produk Bluetti di database ini.');

            return;
        }

        $updated = Product::where('brand_id', $brand->id)
            ->where(fn ($q) => $q->whereNull('affiliate_rate')->orWhere('affiliate_rate', '!=', 5))
            ->update(['affiliate_rate' => 5]);

        $total = Product::where('brand_id', $brand->id)->count();
        $this->command?->info("Fee afiliator Bluetti: {$updated} produk diubah ke 5% (total {$total} produk Bluetti).");
    }
}
