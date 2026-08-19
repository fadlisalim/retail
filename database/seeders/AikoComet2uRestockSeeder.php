<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Pembaruan batch Agustus 2026 untuk AIKO Comet 2U yang SUDAH tayang:
 *  - harga modal diisi Rp 1.850.000 (per keping, semua varian daya);
 *  - stok varian 650 Wp DISETEL ke 20 pcs (angka batch baru — penyetelan
 *    stok yang disengaja, berbeda dari seeder utama yang tidak menyentuh
 *    stok lama);
 *  - harga jual tetap Rp 2.490.000 → margin (2.490.000 - 1.850.000) /
 *    2.490.000 = 25,7%, memenuhi target minimal 25% dari revenue tanpa
 *    menaikkan harga produk yang sudah dipublikasikan (modal ÷ 0,75 =
 *    2.466.667). Idempotent: aman diulang.
 */
class AikoComet2uRestockSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::where('slug', 'panel-surya-aiko-comet-2u')->first();
        if (! $product) {
            $this->command?->warn('Produk panel-surya-aiko-comet-2u tidak ditemukan — jalankan AikoComet2uSeeder dulu.');

            return;
        }

        $product->forceFill(['cost_price' => 1850000])->save();

        $variant = ProductVariant::where('sku', 'AIKO-COMET2U-650')->first();
        if ($variant) {
            $current = (int) WarehouseStock::where('product_variant_id', $variant->id)->sum('quantity_available');
            $delta = 20 - $current;
            if ($delta !== 0) {
                app(StockService::class)->adjust(
                    $product, $variant, $delta, StockMovementType::Purchase,
                    note: 'Batch Agustus 2026 (restock seeder): stok disetel ke 20',
                );
            }
            $this->command?->info("Stok 650 Wp: {$current} → 20 pcs.");
        }

        $this->command?->info('Modal AIKO Comet 2U diisi Rp 1.850.000 — margin di harga Rp 2.490.000 = 25,7%.');
    }
}
