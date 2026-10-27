<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Harga jual Aurora (inverter, baterai, ECHO) diseragamkan ke margin 22% atas modal
 * pricelist EPC 20 Okt 2026 (× 1,11 PPN) — keputusan pemilik 27 Okt 2026. Harga jual =
 * modal ÷ 0,78, dibulatkan ke ATAS kelipatan 50.000, tetap TANPA PPN. Harga coret
 * (sale_price) yang tidak lagi lebih murah dari harga baru dihapus.
 */
class AuroraHargaJualSeeder extends Seeder
{
    public static function sellingPrice(int $cost): int
    {
        return (int) (ceil($cost / 0.78 / 50000) * 50000);
    }

    public function run(): void
    {
        $changed = [];
        foreach (AuroraModalSeeder::EPC as $sku => $epc) {
            $model = Product::where('sku', $sku)->first() ?? ProductVariant::where('sku', $sku)->first();
            if (! $model) {
                continue;
            }
            $cost = AuroraModalSeeder::cost($epc);
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

        $this->command?->info('Harga jual Aurora margin 22%: '.implode('; ', $changed));
    }
}
