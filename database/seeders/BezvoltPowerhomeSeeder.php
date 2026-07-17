<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the "BEZVOLT POWERHOME 6-05" bundle product. Idempotent (keyed by slug),
 * so it can be re-run safely. Images & the datasheet PDF are uploaded manually
 * via the admin (binary files can't be seeded); the YouTube video is included.
 */
class BezvoltPowerhomeSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::firstOrCreate(
            ['slug' => 'bezvolt'],
            ['name' => 'BEZVOLT', 'is_active' => true],
        );

        $category = Category::where('slug', 'paket-plts')->first();

        $description = <<<'HTML'
<p><strong>BEZVOLT POWERHOME 6-05</strong><br>Paket Bundling Hybrid Inverter 6kW + Baterai Lithium LiFePO₄ 5.12kWh</p>
<p>Nikmati solusi penyimpanan energi rumah yang lengkap dalam satu paket. BEZVOLT POWERHOME 6-05 menggabungkan Hybrid Inverter 6kW dan Baterai Lithium LiFePO₄ 5.12kWh sehingga rumah tetap mendapatkan pasokan listrik yang stabil, hemat, dan otomatis saat listrik PLN padam.</p>
<h4>Keunggulan</h4>
<ul>
<li>Garansi Resmi 5 Tahun</li>
<li>Hybrid On-Grid &amp; Off-Grid</li>
<li>Backup Otomatis Saat PLN Padam (&lt;10ms)</li>
<li>Dual MPPT Tracker</li>
<li>Monitoring Real-Time via LCD &amp; Aplikasi</li>
<li>BMS Cerdas dengan Proteksi Lengkap</li>
<li>Sel Baterai LiFePO₄ Grade A</li>
<li>Dapat diparalel hingga 6 unit baterai</li>
<li>Desain Wall Mounted yang ringkas dan elegan</li>
<li>Cocok untuk rumah, villa, ruko, kantor, klinik, hingga UMKM</li>
</ul>
<h4>Isi Paket</h4>
<ul>
<li>Hybrid Inverter BEZVOLT 6kW (Single Phase)</li>
<li>Baterai Lithium LiFePO₄ BEZVOLT 5.12kWh (51.2V 100Ah)</li>
<li>Kabel komunikasi &amp; aksesoris standar</li>
</ul>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Daya Output</th><td>6000 W</td></tr>
<tr><th>Max Input PV</th><td>9000 Wp</td></tr>
<tr><th>Max Charging Current</th><td>120 A</td></tr>
<tr><th>MPPT</th><td>Dual MPPT</td></tr>
<tr><th>Tegangan Baterai</th><td>51.2 V</td></tr>
<tr><th>Transfer Time</th><td>&lt;10 ms</td></tr>
<tr><th>Proteksi</th><td>IP65</td></tr>
<tr><th>Kapasitas Baterai</th><td>5.12 kWh (100 Ah)</td></tr>
<tr><th>Teknologi Baterai</th><td>LiFePO₄ Grade A</td></tr>
<tr><th>Expandable</th><td>Hingga 6 unit</td></tr>
<tr><th>Garansi</th><td>5 Tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bezvolt-powerhome-6-05'],
            [
                'sku' => 'BEZVOLT-POWERHOME-6-05',
                'name' => 'BEZVOLT POWERHOME 6-05 (Paket Bundling Inverter + Baterai)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'POWERHOME 6-05',
                'product_type' => 'bundle',
                'condition' => 'new',
                'short_description' => 'Paket bundling Hybrid Inverter 6kW + Baterai Lithium LiFePO₄ 5.12kWh. Backup otomatis <10ms, hemat tagihan, garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 39000000,
                'sale_price' => null,
                'unit' => 'paket',
                'is_taxable' => true,
                'price_includes_tax' => true,
                'weight_grams' => 65000,
                'requires_freight' => true,
                'warranty' => 'Garansi resmi 5 tahun',
                'is_purchasable' => true,
                'is_featured' => true,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BEZVOLT POWERHOME 6-05 — Paket Inverter Hybrid 6kW + Baterai 5.12kWh',
                'meta_description' => 'Paket bundling BEZVOLT Hybrid Inverter 6kW dan Baterai Lithium LiFePO₄ 5.12kWh. Backup otomatis, hemat listrik, garansi resmi 5 tahun.',
            ],
        );

        // YouTube product video (idempotent).
        $product->videos()->firstOrCreate(
            ['url' => 'https://www.youtube.com/watch?v=mQpi8Jgi1eA'],
            ['title' => 'BEZVOLT POWERHOME 6-05'],
        );

        // Set stock through the warehouse ledger (keeps ledger & cache consistent).
        // Seed opening stock only for a newly created product — never reset admin's stock on re-runs.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 5);
        }

        $this->command?->info('Produk BEZVOLT POWERHOME 6-05 berhasil ditambahkan/diperbarui (slug: '.$product->slug.').');
        $this->command?->warn('Ingat: upload 4 gambar produk + PDF datasheet lewat Admin → Produk → Edit.');
    }

    /** Reconcile the warehouse ledger so the product's available stock equals $target. */
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
