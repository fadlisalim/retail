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
 * Seeds the SRNE SR-DH100 15A PWM solar charge controller. Riset web: ini
 * controller PJU/lampu jalan all-in-one (PWM charger + LED driver step-up
 * built-in, konfigurasi via remote 2.4G/IR, TANPA LCD/USB) — dideskripsikan
 * apa adanya. Spek dari halaman resmi SRNE (DH series) + cross-check reseller;
 * angka estimasi ditandai. Harga jual Rp 570.000, stok 10. Idempotent.
 */
class SccSrneDh100Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'solar-charge-controller-pwm')->first();
        $pju = Category::where('slug', 'pju-tenaga-surya')->first();
        $brand = Brand::firstOrCreate(['slug' => 'srne'], ['name' => 'SRNE', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>SRNE SR-DH100 15A</strong> — solar charge controller <strong>PWM 12V/24V (auto)</strong> seri DH: controller <strong>all-in-one untuk PJU / lampu jalan tenaga surya</strong> dengan <strong>LED driver step-up (boost) constant-current built-in</strong>. Satu unit langsung mengatur pengisian baterai dari panel surya sekaligus menyalakan lampu LED — tanpa driver terpisah.</p>
<h4>Keunggulan</h4>
<ul>
<li>⚡ <strong>PWM 15A, 12V/24V auto</strong> — input panel surya hingga 55V.</li>
<li>💡 <strong>LED driver built-in</strong> 50–3000mA (constant-current, efisiensi hingga 96%), output beban ±50W @12V / ±100W @24V.</li>
<li>🔋 Mendukung baterai <strong>Lead-Acid (Flooded/AGM/GEL) dan Lithium</strong> — parameter bisa diatur.</li>
<li>📡 <strong>Setting via remote 2.4G/infrared</strong> (tanpa LCD/tombol di unit): mode light-control (nyala saat gelap), <strong>dimming 9 periode</strong>, pre-dawn, opsi sensor gerak.</li>
<li>🌧️ <strong>Casing metal kedap air (IP67)</strong> — aman dipasang outdoor di tiang PJU.</li>
<li>🛡️ Proteksi lengkap: overcharge, over-discharge, polaritas baterai terbalik, LED short/open circuit, over-temperature. Menyimpan log status 7 hari.</li>
</ul>
<h4>Cocok Untuk</h4>
<ul>
<li>PJU / lampu jalan tenaga surya (retrofit maupun rakitan baru)</li>
<li>Lampu taman & lampu sorot tenaga surya</li>
<li>Sistem solar 12V/24V dengan beban lampu LED</li>
</ul>
<p><em>Catatan: unit ini dirancang untuk beban lampu LED (bukan SCC display-LCD/USB untuk beban umum). Konfigurasi memerlukan remote DH series (dijual terpisah bila belum punya — tanyakan ke CS). *Sebelum order, sebaiknya chat/tanya ketersediaan stok terlebih dahulu.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Merek</th><td>SRNE</td></tr>
<tr><th>Model</th><td>SR-DH100 (seri DH)</td></tr>
<tr><th>Tipe</th><td>PWM + LED driver step-up constant-current (controller PJU all-in-one)</td></tr>
<tr><th>Tegangan Sistem</th><td>12V / 24V (auto)</td></tr>
<tr><th>Arus Pengisian Maks</th><td>15 A</td></tr>
<tr><th>Tegangan Input PV Maks</th><td>≤ 55 V</td></tr>
<tr><th>Daya Panel Maks (estimasi)</th><td>±200 Wp @12V / ±400 Wp @24V</td></tr>
<tr><th>Arus Output LED</th><td>50–3000 mA (constant-current, dapat diatur)</td></tr>
<tr><th>Daya Beban LED</th><td>±50 W @12V / ±100 W @24V</td></tr>
<tr><th>Efisiensi Driver</th><td>Hingga 96%</td></tr>
<tr><th>Tipe Baterai</th><td>Lead-Acid (Flooded/AGM/GEL) &amp; Lithium</td></tr>
<tr><th>Konfigurasi</th><td>Remote 2.4G / infrared (light control, dimming 9 periode, pre-dawn, jam kerja 0–15 jam, opsi sensor gerak)</td></tr>
<tr><th>Proteksi</th><td>Overcharge, over-discharge, polaritas terbalik, LED short/open circuit, over-temperature</td></tr>
<tr><th>Log Data</th><td>Status sistem hingga 7 hari</td></tr>
<tr><th>Rating Kedap Air</th><td>IP67 (casing metal, potted)</td></tr>
<tr><th>Suhu Operasi</th><td>-35°C s/d +65°C</td></tr>
<tr><th>Dimensi</th><td>±100 × 82 × 20 mm</td></tr>
<tr><th>Berat</th><td>±280 g</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'solar-charge-controller-pwm-srne-sr-dh100-15a'],
            [
                'sku' => 'SRNE-SR-DH100-15A',
                'name' => 'SCC Solar Charge Controller PWM SRNE SR-DH100 15A (12V/24V, All-in-One PJU)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'SR-DH100',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'SCC PWM SRNE SR-DH100 15A 12V/24V auto dengan LED driver step-up built-in — controller all-in-one untuk PJU/lampu jalan tenaga surya. Support baterai lead-acid & lithium, IP67, setting via remote.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 570000,
                'unit' => 'pcs',
                'weight_grams' => 300,
                'length_cm' => 10.0,
                'width_cm' => 8.2,
                'height_cm' => 2.0,
                'warranty' => 'Garansi 1 Tahun',
                'keywords' => 'scc, solar charge controller, pwm, srne, dh100, sr-dh100, 15a, pju, lampu jalan, street light, led driver, controller pju',
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Solar Charge Controller PWM SRNE SR-DH100 15A (12V/24V) — Controller PJU All-in-One',
                'meta_description' => 'Jual SCC PWM SRNE SR-DH100 15A 12V/24V dengan LED driver built-in untuk PJU/lampu jalan tenaga surya. IP67, support lithium, Rp 570.000.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            // Tampil juga di kategori PJU Tenaga Surya (multi-kategori).
            $product->categories()->sync(array_filter([$category?->id, $pju?->id]));
            $this->setStock($product, 10);
        }

        $this->command?->info('Produk SRNE SR-DH100 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 570.000 • Stok awal 10 pcs. Upload gambar lewat Admin → Produk → Edit.');
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
