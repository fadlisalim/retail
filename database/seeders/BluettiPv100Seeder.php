<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds the BLUETTI PV100 foldable monocrystalline solar panel (100W).
 * Struck price Rp 3.269.000 → sale Rp 2.942.100, stok 2. Specs sourced from the
 * official BLUETTI PV100 listing (support.bluettipower.com) + reputable retailer
 * datasheets. Distributor SKU P-P100-UN-YL-BL-020-P0. Idempotent (firstOrCreate).
 * Images uploaded via admin.
 */
class BluettiPv100Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI PV100 — Panel Surya Portabel Lipat 100W</strong><br>Sumber listrik tenaga surya yang ringkas dan siap dibawa ke mana saja. Panel <strong>monocrystalline 100W</strong> dengan efisiensi sel hingga <strong>23,4%</strong> ini dirancang untuk mengisi daya power station BLUETTI maupun perangkat lain berkonektor <strong>MC4 universal</strong>. Desain lipat 4 bagian membuatnya mudah dibawa saat camping, road trip, berkebun, hingga sebagai cadangan energi darurat.</p>
<h4>Keunggulan</h4>
<ul>
<li>☀️ <strong>Efisiensi tinggi hingga 23,4%</strong> — sel monocrystalline premium memanen daya maksimal dari sinar matahari.</li>
<li>🌊 <strong>Lapisan ETFE tahan cuaca</strong> — permukaan kuat, anti gores, tahan percikan air &amp; sinar UV (junction box IP67).</li>
<li>🎒 <strong>Mudah dilipat &amp; dibawa</strong> — desain 4 lipatan yang ringkas, bobot hanya 5,7 kg dengan handle praktis.</li>
<li>📐 <strong>Kickstand adjustable</strong> — penyangga bawaan untuk mengatur sudut optimal ke arah matahari.</li>
<li>🔌 <strong>Konektor MC4 universal</strong> — kompatibel dengan power station BLUETTI dan perangkat lain berkonektor MC4.</li>
<li>🪶 <strong>Ringan &amp; portabel</strong> — solusi energi hijau tanpa suara, tanpa emisi, di mana pun Anda berada.</li>
</ul>
<p><em>Garansi Resmi 2 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI PV100</td></tr>
<tr><th>Daya</th><td>100 W</td></tr>
<tr><th>Efisiensi Sel</th><td>Hingga 23,4%</td></tr>
<tr><th>Tipe Sel</th><td>Monocrystalline (lapisan ETFE)</td></tr>
<tr><th>Vmp / Voc</th><td>18,8V / 23,7V</td></tr>
<tr><th>Imp / Isc</th><td>5,3A / 5,8A</td></tr>
<tr><th>Konektor</th><td>MC4 standar (universal)</td></tr>
<tr><th>Jumlah Lipatan</th><td>4 lipatan</td></tr>
<tr><th>Dimensi Terlipat</th><td>52,5 × 52,0 × 6,5 cm</td></tr>
<tr><th>Dimensi Terbuka</th><td>52,5 × 226,5 cm</td></tr>
<tr><th>Berat</th><td>5,7 kg</td></tr>
<tr><th>Tahan Air / IP</th><td>IP67 (junction box) — hindari paparan hujan/salju langsung</td></tr>
<tr><th>Garansi</th><td>Resmi 2 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-pv100'],
            [
                'sku' => 'BLUETTI-PV100',
                'name' => 'BLUETTI PV100 Panel Surya Portabel Lipat 100W',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'PV100',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Panel surya portabel lipat BLUETTI PV100, monocrystalline 100W, efisiensi hingga 23,4%, konektor MC4 universal, bobot 5,7 kg. Garansi resmi 2 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 3269000,        // Harga coret
                'sale_price' => 2942100,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 5700,
                'length_cm' => 52.5,
                'width_cm' => 52,
                'height_cm' => 6.5,
                'warranty' => 'Garansi Resmi 2 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI PV100 100W — Panel Surya Portabel Lipat Monocrystalline',
                'meta_description' => 'Jual BLUETTI PV100 panel surya portabel lipat 100W monocrystalline, efisiensi 23,4%, konektor MC4. Rp 2.942.100 (dari Rp 3.269.000). Garansi resmi 2 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 2);
        }

        $this->command?->info('Produk BLUETTI PV100 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 3.269.000 → Rp 2.942.100 • Stok awal 2 • Garansi 2 tahun. Upload gambar lewat Admin → Produk → Edit.');
    }

    private function setStock(Product $product, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)
            ->whereNull('product_variant_id')->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, null, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder)');
        }
    }
}
