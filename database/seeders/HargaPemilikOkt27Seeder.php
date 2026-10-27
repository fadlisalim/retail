<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Harga jual yang ditetapkan langsung pemilik (27 Okt 2026), TANPA PPN:
 *  - ECHO-8 disamakan harga retail Aurora Rp 33.250.000 (margin ±10% atas modal EPC × 1,11);
 *  - AIKO Comet 2U 640 Wp naik ke Rp 2.750.000 (margin ±33% atas modal 1.850.000).
 * Harga coret yang tidak lagi lebih murah dari harga baru dihapus; produk ber-varian
 * ikut harga varian aktif termurah. Sekali jalan (deploy/seeders.txt).
 */
class HargaPemilikOkt27Seeder extends Seeder
{
    public const PRICES = [
        'AURORA-ECHO-8' => 33_250_000,
        'AIKO-COMET2U-640' => 2_750_000,
    ];

    public function run(): void
    {
        foreach (self::PRICES as $sku => $price) {
            $model = ProductVariant::where('sku', $sku)->first() ?? Product::where('sku', $sku)->first();
            if (! $model) {
                $this->command?->warn("{$sku} tidak ditemukan — dilewati.");

                continue;
            }
            $old = (int) $model->price;
            $model->forceFill([
                'price' => $price,
                'sale_price' => $model->sale_price !== null && (int) $model->sale_price < $price ? $model->sale_price : null,
            ])->save();

            if ($model instanceof ProductVariant && $model->product) {
                $product = $model->product;
                $cheapest = $product->variants()->where('is_active', true)->orderBy('price')->first();
                $product->forceFill([
                    'price' => $cheapest->price,
                    'sale_price' => $product->sale_price !== null && (int) $product->sale_price < (int) $cheapest->price ? $product->sale_price : null,
                ])->save();
            }

            $this->command?->info($sku.': '.number_format($old, 0, ',', '.').' → '.number_format($price, 0, ',', '.'));
        }
    }
}
