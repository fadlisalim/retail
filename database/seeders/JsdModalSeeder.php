<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\SeedGuard;
use Illuminate\Database\Seeder;

/**
 * Harga modal JSD Solar dari pricelist PT JSD Solar Energi (1 Agustus 2026, EXW Jakarta),
 * diberikan langsung oleh pemilik. Pembelian bisa TANPA PPN → modal = harga pricelist apa
 * adanya. Hanya mengisi modal; harga jual tidak diubah. Modal yang sudah disunting admin
 * tidak ditimpa (SeedGuard).
 */
class JsdModalSeeder extends Seeder
{
    /** SKU produk/varian di toko => harga pricelist (tanpa PPN). */
    public const PRICELIST = [
        'JSD-J1200HC' => 2_450_000,
        'JSD-J2500HC' => 2_975_000,
        'JSD-J4000E' => 4_950_000,
        'JSD-J6200HP' => 6_750_000,
        'JSD-J6500HC' => 5_850_000,
        'JSD-J11100HPC' => 9_750_000,
        'JSD-WIFI-PLUG-PRO' => 450_000,
        'JSD-JHP5000' => 22_500_000,
        'JSD-XHP12K15' => 8_350_000,
        'JSD-XHP4K30' => 14_250_000,
        'JSD-XHP65K60' => 23_000_000,
        'JSD-BG48100' => 12_000_000,
        'JSD-LFP48200' => 23_000_000,
        'JSD-J4U48100' => 12_000_000,
        'JSD-LD48314' => 27_650_000,
        'JSD-J12100' => 3_050_000,
        'JSD-J12200' => 5_950_000,
        'JSD-J24100' => 5_950_000,
    ];

    public function run(): void
    {
        $filled = 0;
        $kept = [];
        $missing = [];
        foreach (self::PRICELIST as $sku => $cost) {
            $model = Product::where('sku', $sku)->first() ?? ProductVariant::where('sku', $sku)->first();
            if (! $model) {
                $missing[] = $sku;

                continue;
            }
            $guard = new SeedGuard('jsd-modal:'.$sku);
            if ($guard->apply($model, ['cost_price' => $cost])) {
                $model->save();
                $filled++;
            } else {
                $kept[] = $sku;
            }
            $guard->commit();

            // Produk ber-varian: modal di level produk ikut varian termurah bila masih kosong.
            if ($model instanceof ProductVariant && $model->product && blank($model->product->cost_price)) {
                $cheapest = $model->product->variants()->whereNotNull('cost_price')->orderBy('price')->first();
                $model->product->forceFill(['cost_price' => $cheapest?->cost_price])->save();
            }
        }

        $this->command?->info("Modal JSD (pricelist Agustus 2026, tanpa PPN): {$filled} diisi".($kept ? ', dipertahankan (sudah diisi admin): '.implode(', ', $kept) : '').($missing ? ', tidak ditemukan: '.implode(', ', $missing) : '').'.');
    }
}
