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
 * Seeds the "BEZVOLT Hybrid Inverter 6kW Single Phase" product. Idempotent
 * (keyed by slug). Upload the datasheet PDF & product images manually via admin.
 */
class BezvoltInverter6kwSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::firstOrCreate(['slug' => 'bezvolt'], ['name' => 'BEZVOLT', 'is_active' => true]);
        $category = Category::where('slug', 'inverter')->first();

        $description = <<<'HTML'
<p><strong>BEZVOLT Hybrid Inverter 6.000W (6 kW) Single Phase</strong><br>Inverter Hybrid Pintar untuk PLTS, Backup Listrik, dan Hemat Energi</p>
<p>BEZVOLT Hybrid Inverter 6.000W adalah inverter multifungsi yang menggabungkan panel surya (PLTS), PLN, baterai lithium, dan genset dalam satu sistem cerdas. Cocok untuk rumah, villa, kantor, ruko, dan usaha yang membutuhkan listrik stabil, hemat, dan tetap menyala saat PLN padam.</p>
<p>Dengan teknologi MPPT ganda, monitoring real-time, serta waktu transfer sangat cepat (&lt;10 ms), sistem ini memungkinkan rumah tetap beroperasi tanpa gangguan saat terjadi pemadaman listrik.</p>
<h4>Keunggulan Utama</h4>
<ul>
<li>⚡ Daya nominal 6.000 Watt (6 kW)</li>
<li>🔋 Support baterai Lithium, GEL, AGM, dan Lead Acid</li>
<li>☀️ Dual MPPT untuk efisiensi panel surya lebih maksimal</li>
<li>🏠 Fungsi hybrid: PLTS + PLN + Baterai + Genset</li>
<li>📱 Monitoring real-time via LCD dan aplikasi</li>
<li>🔄 Transfer otomatis sangat cepat (&lt;10 ms)</li>
<li>🔌 Bisa paralel hingga 6 unit (off-grid)</li>
<li>🛡️ Proteksi lengkap dan sistem pendingin pintar</li>
<li>✅ Garansi resmi 5 tahun</li>
</ul>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Merk</th><td>BEZVOLT</td></tr>
<tr><th>Tipe / Spec</th><td>S6K-SL-S Single Phase Solar Hybrid Inverter 6kW, Battery Voltage 48V, IP65 Silicon Carbide</td></tr>
<tr><th>Jenis</th><td>1 Phase</td></tr>
<tr><th>Daya Output</th><td>6000 W (6 kW)</td></tr>
<tr><th>MPPT</th><td>Dual MPPT</td></tr>
<tr><th>Transfer Time</th><td>&lt;10 ms</td></tr>
<tr><th>Proteksi</th><td>IP65</td></tr>
<tr><th>Satuan</th><td>pcs</td></tr>
<tr><th>Garansi</th><td>5 Tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::updateOrCreate(
            ['slug' => 'bezvolt-hybrid-inverter-6kw-single-phase'],
            [
                'sku' => 'BEZVOLT-INV-6KW-S6K',
                'name' => 'BEZVOLT Hybrid Inverter 6.000W (6 kW) Single Phase Premium Quality',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'S6K-SL-S',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Inverter hybrid 6kW single phase untuk PLTS + PLN + Baterai + Genset. Dual MPPT, transfer <10ms, bisa paralel 6 unit. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 17945000,
                'sale_price' => null,
                'unit' => 'pcs',
                'weight_grams' => 18000,
                'requires_freight' => true,
                'warranty' => 'Garansi resmi 5 tahun',
                'is_featured' => true,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BEZVOLT Hybrid Inverter 6kW Single Phase — Dual MPPT, IP65',
                'meta_description' => 'Inverter hybrid BEZVOLT 6.000W (6 kW) single phase untuk PLTS, PLN, baterai & genset. Dual MPPT, transfer <10 ms, paralel hingga 6 unit. Garansi 5 tahun.',
            ],
        );

        // Set stock through the warehouse ledger (keeps ledger & cache consistent).
        $this->setStock($product, 8);

        $this->command?->info('Produk BEZVOLT Hybrid Inverter 6kW berhasil ditambahkan/diperbarui (slug: '.$product->slug.').');
        $this->command?->warn('Ingat: upload gambar produk + PDF datasheet (& video YouTube bila ada) lewat Admin → Produk → Edit.');
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
