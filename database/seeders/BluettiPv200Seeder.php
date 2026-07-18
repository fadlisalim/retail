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
 * Seeds the BLUETTI PV200 foldable portable monocrystalline solar panel (200W).
 * Struck price Rp 4.099.000 → sale Rp 3.689.100, stok 2. Distributor SKU
 * P-P200-UN-YL-BL-010-P0. Specs sourced from the official BLUETTI PV200 user
 * manual (bluetti.com PV200-User-Manual) + reputable retailers (efisiensi sel
 * 23,4%, MC4, IP65). Idempotent (firstOrCreate). Images uploaded via admin.
 */
class BluettiPv200Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI PV200 — Panel Surya Portabel Lipat 200W</strong><br>Sumber energi surya di mana pun Anda berada. Panel surya <strong>monocrystalline 200W</strong> dengan efisiensi sel hingga <strong>23,4%</strong> ini dirancang lipat empat yang ringkas, tahan air, dan mudah dibawa. Pasangan sempurna untuk mengisi ulang power station BLUETTI Anda saat camping, road trip, berkebun, hingga situasi darurat off-grid.</p>
<h4>Keunggulan</h4>
<ul>
<li>☀️ <strong>Efisiensi tinggi hingga 23,4%</strong> — sel monocrystalline premium dengan laminasi ETFE tahan lama, menyerap lebih banyak energi matahari.</li>
<li>🌧️ <strong>Tahan air IP65</strong> — aman dari cipratan air &amp; hujan ringan, siap diajak beraktivitas di alam terbuka.</li>
<li>🎒 <strong>Mudah dilipat &amp; dibawa</strong> — desain lipat empat, terlipat hanya 59 × 63 cm dengan bobot 7,3 kg, dilengkapi handle untuk portabilitas maksimal.</li>
<li>📐 <strong>Kickstand yang dapat diatur</strong> — dudukan lipat memudahkan pengaturan sudut agar tegak lurus terhadap matahari untuk hasil optimal.</li>
<li>🔌 <strong>Konektor MC4 universal</strong> — kompatibel dengan mayoritas power station &amp; solar generator BLUETTI serta merek lain di pasaran.</li>
<li>🔋 <strong>Pasangan ideal power station</strong> — output 200W mempercepat pengisian daya baterai portabel Anda dengan tenaga surya gratis.</li>
</ul>
<p><em>Garansi Resmi 2 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI PV200</td></tr>
<tr><th>Daya Puncak</th><td>200 W</td></tr>
<tr><th>Efisiensi Sel</th><td>Hingga 23,4%</td></tr>
<tr><th>Tipe Sel</th><td>Monocrystalline (laminasi ETFE)</td></tr>
<tr><th>Tegangan (Vmp / Voc)</th><td>20,5V / 26,1V</td></tr>
<tr><th>Arus (Imp / Isc)</th><td>9,7A / 10,3A</td></tr>
<tr><th>Konektor Output</th><td>MC4</td></tr>
<tr><th>Dimensi Terlipat</th><td>590 × 630 mm</td></tr>
<tr><th>Dimensi Terbuka</th><td>590 × 2265 mm</td></tr>
<tr><th>Berat</th><td>7,3 kg</td></tr>
<tr><th>Tahan Air</th><td>IP65</td></tr>
<tr><th>Suhu Operasi</th><td>-10°C ~ +65°C</td></tr>
<tr><th>Garansi</th><td>Resmi 2 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-pv200'],
            [
                'sku' => 'BLUETTI-PV200',
                'name' => 'BLUETTI PV200 Panel Surya Portabel Lipat 200W',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'PV200',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Panel surya portabel lipat BLUETTI PV200, monocrystalline 200W efisiensi hingga 23,4%, tahan air IP65, konektor MC4, kickstand. Bobot 7,3 kg. Garansi resmi 2 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 4099000,        // Harga coret
                'sale_price' => 3689100,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 7300,
                'length_cm' => 63,         // terlipat
                'width_cm' => 59,          // terlipat
                'warranty' => 'Garansi Resmi 2 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI PV200 200W — Panel Surya Portabel Lipat Monocrystalline',
                'meta_description' => 'Jual BLUETTI PV200 panel surya portabel lipat 200W monocrystalline, efisiensi 23,4%, IP65, konektor MC4. Rp 3.689.100 (dari Rp 4.099.000). Garansi resmi 2 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 2);
        }

        $this->command?->info('Produk BLUETTI PV200 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 4.099.000 → Rp 3.689.100 • Stok awal 2 • Garansi 2 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
