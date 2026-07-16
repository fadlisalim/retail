<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds the AIKO Comet 2U (AIKO-G-MCH72Mw) N-Type ABC solar module as a
 * VARIABLE product with wattage variants (640–670 Wp). Only the 650 Wp is
 * currently ready (30 pcs); the other wattages are seeded at 0 stock so they
 * can be enabled when available. Idempotent. Images/PDF uploaded via admin.
 */
class AikoComet2uSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first();

        $description = <<<'HTML'
<p><strong>AIKO Comet 2U — Panel Surya N-Type ABC 640–670 Wp</strong><br>Modul surya generasi terbaru dengan teknologi sel <strong>N-Type ABC (All Back Contact)</strong>: seluruh busbar dipindah ke belakang sehingga permukaan depan lebih bersih menangkap cahaya — efisiensi hingga <strong>24,8%</strong>.</p>
<h4>Keunggulan</h4>
<ul>
<li>☀️ <strong>Higher Power</strong> — output hingga 670 W per panel, lebih sedikit panel untuk daya yang sama.</li>
<li>🌥️ <strong>Partial Shading Optimisation</strong> — tetap produktif meski sebagian panel terkena bayangan.</li>
<li>🌡️ <strong>Temperature Coefficient</strong> lebih baik (−0,26%/°C pada Pmax) — output stabil di cuaca panas.</li>
<li>🛡️ <strong>Micro-crack Resistance</strong> — sel ABC lebih tahan retak mikro.</li>
<li>💧 Junction box <strong>IP68</strong> dengan 3 bypass diode, konektor MC4.</li>
<li>💪 Beban statis depan <strong>5400 Pa</strong>, tahan hujan es 25 mm @ 23 m/s.</li>
</ul>
<h4>Garansi</h4>
<ul>
<li>Garansi produk <strong>15 tahun</strong> (dapat diperpanjang hingga 25 tahun*).</li>
<li>Garansi performa <strong>linear 30 tahun</strong> — degradasi tahun pertama ≤1%, lalu ≤0,35%/tahun (≥88,85% di tahun ke-30).</li>
</ul>
<p><em>*Munich Re coverage opsional, tersedia atas permintaan. Spesifikasi dapat berubah sewaktu-waktu oleh pabrikan.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>AIKO-G-MCH72Mw (Comet 2U)</td></tr>
<tr><th>Tipe Sel</th><td>N-Type ABC (All Back Contact)</td></tr>
<tr><th>Daya Output (Pmax)</th><td>640 – 670 Wp</td></tr>
<tr><th>Efisiensi Modul</th><td>Hingga 24,8%</td></tr>
<tr><th>Toleransi Daya</th><td>0 ~ +3%</td></tr>
<tr><th>Jumlah Sel</th><td>144 (6×24)</td></tr>
<tr><th>Kaca</th><td>Tempered glass 3,2 mm</td></tr>
<tr><th>Frame</th><td>Aluminium anodized</td></tr>
<tr><th>Dimensi</th><td>2382 × 1134 × 30 mm</td></tr>
<tr><th>Berat</th><td>27,1 kg ±3%</td></tr>
<tr><th>Junction Box</th><td>IP68, 3 bypass diode</td></tr>
<tr><th>Konektor</th><td>MC4 Compatible / MC4-EVO2A</td></tr>
<tr><th>Tegangan Sistem Maks</th><td>DC 1500 V</td></tr>
<tr><th>Sekring Seri Maks</th><td>25 A</td></tr>
<tr><th>Beban Statis Maks</th><td>Depan 5400 Pa • Belakang 2400 Pa</td></tr>
<tr><th>Koef. Suhu Pmax</th><td>−0,26%/°C</td></tr>
<tr><th>Suhu Operasi</th><td>−40°C – +85°C</td></tr>
<tr><th>Garansi Produk</th><td>15 tahun (extendable 25 tahun)</td></tr>
<tr><th>Garansi Performa</th><td>Linear 30 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::updateOrCreate(
            ['slug' => 'panel-surya-aiko-comet-2u'],
            [
                'sku' => 'AIKO-COMET2U',
                'name' => 'Panel Surya AIKO Comet 2U N-Type ABC (640–670 Wp)',
                'category_id' => $category?->id,
                'model' => 'AIKO-G-MCH72Mw',
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Panel surya AIKO Comet 2U N-Type ABC, efisiensi hingga 24,8%. Daya 640–670 Wp, garansi produk 15 tahun & performa linear 30 tahun. Ready 650 Wp.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 2490000,   // per varian (650 Wp yang ready)
                'sale_price' => null,
                'unit' => 'pcs',
                'weight_grams' => 27100,
                'length_cm' => 238.2,
                'width_cm' => 113.4,
                'height_cm' => 3,
                'requires_freight' => true,
                'warranty' => 'Garansi produk 15 tahun • performa linear 30 tahun',
                'is_featured' => true,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Panel Surya AIKO Comet 2U 650 Wp — N-Type ABC 24,8%',
                'meta_description' => 'Jual panel surya AIKO Comet 2U (AIKO-G-MCH72Mw) N-Type ABC 640–670 Wp, efisiensi 24,8%. Ready 650 Wp. Garansi produk 15 tahun, performa 30 tahun.',
            ],
        );

        // Wattage variants. Only 650 Wp is ready (30 pcs); others start at 0.
        // Efficiency figures per datasheet (STC).
        $variants = [
            ['wp' => 640, 'eff' => '23,7%', 'stock' => 0],
            ['wp' => 645, 'eff' => '23,9%', 'stock' => 0],
            ['wp' => 650, 'eff' => '24,1%', 'stock' => 30],
            ['wp' => 655, 'eff' => '24,2%', 'stock' => 0],
            ['wp' => 660, 'eff' => '24,4%', 'stock' => 0],
            ['wp' => 665, 'eff' => '24,6%', 'stock' => 0],
            ['wp' => 670, 'eff' => '24,8%', 'stock' => 0],
        ];

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => 'AIKO-COMET2U-'.$v['wp']],
                [
                    'product_id' => $product->id,
                    'name' => $v['wp'].' Wp',
                    'option_values' => ['Daya' => $v['wp'].' Wp'],
                    'price' => 2490000,
                    'sale_price' => null,
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
            $this->setVariantStock($product, $variant, $v['stock']);
        }

        $this->command?->info('Produk AIKO Comet 2U (7 varian daya) berhasil ditambahkan/diperbarui (slug: '.$product->slug.').');
        $this->command?->warn('Ready: 650 Wp = 30 pcs. Varian lain stok 0 — aktifkan saat tersedia.');
        $this->command?->warn('Ingat: upload gambar produk + PDF datasheet lewat Admin → Produk → Edit.');
    }

    private function setVariantStock(Product $product, ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_variant_id', $variant->id)->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, $variant, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder)');
        }
    }
}
