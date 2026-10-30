<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Harga jual JSD Solar diseragamkan ke margin 25% atas modal pricelist Agustus 2026
 * (tanpa PPN) — keputusan pemilik 30 Okt 2026. Harga jual = modal ÷ 0,75, dibulatkan ke
 * ATAS kelipatan 50.000, tetap TANPA PPN. Harga coret yang tidak lagi lebih murah dari
 * harga baru dihapus. Sekali jalan (deploy/seeders.txt).
 */
class JsdHargaJualSeeder extends Seeder
{
    public static function sellingPrice(int $cost): int
    {
        return (int) (ceil($cost / 0.75 / 50000) * 50000);
    }

    public function run(): void
    {
        $changed = [];
        foreach (JsdModalSeeder::PRICELIST as $sku => $cost) {
            $model = Product::where('sku', $sku)->first() ?? ProductVariant::where('sku', $sku)->first();
            if (! $model) {
                continue;
            }
            $price = self::sellingPrice($cost);
            $old = (int) $model->price;
            $model->forceFill([
                'cost_price' => $cost,
                'price' => $price,
                'sale_price' => $model->sale_price !== null && (int) $model->sale_price < $price ? $model->sale_price : null,
            ])->save();
            $changed[] = $sku.' '.number_format($old, 0, ',', '.').' → '.number_format($price, 0, ',', '.');

            // Produk ber-varian: harga produk = varian aktif termurah.
            if ($model instanceof ProductVariant && $model->product) {
                $cheapest = $model->product->variants()->where('is_active', true)->orderBy('price')->first();
                $model->product->forceFill(['price' => $cheapest->price, 'cost_price' => $cheapest->cost_price])->save();
            }
        }

        $this->command?->info('Harga jual JSD margin 25%: '.implode('; ', $changed));
    }
}
