<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\SeedGuard;
use Illuminate\Database\Seeder;

/**
 * Harga modal Aurora dari pricelist EPC Partner PT Aurora Power Energy Global
 * (periode 20 Oktober 2026), diberikan langsung oleh pemilik. Modal = kolom paling
 * kanan (EPC Special Price) × 1,11 — supplier memungut PPN, sedangkan harga jual
 * energi.click selalu tanpa PPN. Hanya mengisi modal; harga jual tidak diubah.
 * Modal yang sudah disunting admin tidak ditimpa (SeedGuard).
 */
class AuroraModalSeeder extends Seeder
{
    /** SKU produk/varian di toko => harga EPC (tanpa PPN). */
    public const EPC = [
        'YF-FS-HP-1612-D' => 3_400_000,
        'YF-FS-HP-4224-D' => 4_515_000,
        'YF-FS-HP-6248' => 6_695_000,
        'YF-FS-HP-11048' => 14_395_000,
        'YF-FS-HP-11048-D' => 11_695_000,
        'YF-FS-WP1512' => 2_385_000,
        'YF-FS-WM3024' => 4_725_000,
        'YF-FS-WM6348' => 6_885_000,
        'YF-FS-WM-6348-P' => 7_295_000,
        'YF-FS-WM-12548-P' => 14_985_000,
        'YF-TP-HV3P-40K' => 107_035_000,
        'YF-TP-HV3P-50K' => 109_335_000,
        'YF-TP-HV3P-60K' => 114_335_000,
        'YF-LV-P3E-8048' => 33_335_000,
        'YF-LV-P3E-10048' => 34_550_000,
        'YF-LV-P3E-12048' => 35_625_000,
        'YF-LV-P3E-15048' => 39_015_000,
        'YF-LV-P3E-20048' => 50_250_000,
        'YF-LV-P3E-24048' => 54_725_000,
        'YF-LFP-128200' => 6_650_000,
        'YF-LFP-256100' => 6_650_000,
        'YF-LFP-128314' => 7_195_000,
        'YF-LFP-5KWH-24V' => 12_750_000,
        'YF-LFP-5KWH-48V' => 13_250_000,
        'YF-LFP-10.5KWH-48V' => 23_825_000,
        'YF-LFP-15KWH-48V' => 27_350_000,
        'YF-LFP-21.5KWH-CES' => 51_850_000,
        'YF-LFP-30KWH-CES' => 64_765_000,
        'AURORA-ECHO-1' => 5_200_000,
        'AURORA-ECHO-2' => 7_500_000,
        'AURORA-ECHO-8' => 26_975_000,
    ];

    public static function cost(int $epc): int
    {
        return (int) round($epc * 1.11);
    }

    public function run(): void
    {
        $filled = 0;
        $kept = [];
        $missing = [];
        foreach (self::EPC as $sku => $epc) {
            $model = Product::where('sku', $sku)->first() ?? ProductVariant::where('sku', $sku)->first();
            if (! $model) {
                $missing[] = $sku;

                continue;
            }
            $guard = new SeedGuard('aurora-modal:'.$sku);
            if ($guard->apply($model, ['cost_price' => self::cost($epc)])) {
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

        $this->command?->info("Modal Aurora (EPC × 1,11): {$filled} diisi".($kept ? ', dipertahankan (sudah diisi admin): '.implode(', ', $kept) : '').($missing ? ', tidak ditemukan: '.implode(', ', $missing) : '').'.');
    }
}
