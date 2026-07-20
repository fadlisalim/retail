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
 * Seeds the BLUETTI Apex 300 modular power station (2764.8Wh / 3840W LiFePO4).
 * Struck price Rp 35.889.000 → sale Rp 32.299.050, stok 1. Reseller SKU
 * P-APEX300-EU-GY-BL-010. Specs sourced from the official BLUETTI datasheet
 * (bluettipower.com / support.bluettipower.com Apex 300 spec sheet & User
 * Manual V3.0) plus reputable retailer/reviewer sheets. EU/230V variant
 * (cocok untuk jaringan listrik Indonesia). Idempotent (firstOrCreate).
 * Images uploaded via admin.
 */
class BluettiApex300Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI Apex 300 — Power Station Modular 2764,8Wh / 3840W</strong><br>Flagship sistem energi modular BLUETTI (2025). Baterai <strong>LiFePO4 grade otomotif 2.764,8Wh</strong> dengan output raksasa <strong>3840W Pure Sine Wave</strong> (lonjakan hingga 7680W), dirancang untuk backup listrik rumah, RV/caravan, workshop, hingga sumber daya off-grid. Super ekspandabel — dari 2,76 kWh hingga <strong>58 kWh</strong> saat digabung dengan baterai ekspansi &amp; unit paralel.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 2.764,8Wh (grade otomotif)</strong> — 6.000+ siklus pengisian ke 80%, umur pakai hingga ±17 tahun.</li>
<li>⚡ <strong>Output 3840W</strong> (lonjakan 7680W) — sanggup menyalakan AC, pompa air, mesin cuci, hingga peralatan daya tinggi secara bersamaan.</li>
<li>🌊 <strong>Pure Sine Wave 230V / 50Hz</strong> — arus bersih &amp; stabil seperti listrik PLN, aman untuk elektronik sensitif.</li>
<li>🔗 <strong>Sangat ekspandabel</strong> — hingga ±19,3 kWh per unit (maks 6 baterai ekspansi B300K), dan hingga <strong>58 kWh / 11,52kW</strong> dengan 3 unit paralel (Hub A1).</li>
<li>☀️ <strong>Pengisian surya</strong> — hingga 1200W solar langsung (12–150V), dan hingga 4000W dengan SolarX 4K.</li>
<li>🔌 <strong>6 stopkontak AC</strong> pada unit; port DC/USB (2× USB-C 100W, 2× USB-A, 2× port mobil 12V) tersedia via aksesori opsional Power Hub D1.</li>
<li>🚀 <strong>Pengisian AC TurboBoost</strong> — hingga 3840W, 0–100% dalam ±65 menit.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil untuk pemakaian harian jangka panjang.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan &amp; efisiensi)</small></h4>
<ul>
<li>❄️ Kulkas (150W): ±15 jam</li>
<li>🌀 AC 1 PK (±700W): ±3 jam</li>
<li>📺 TV (60W): ±39 jam</li>
<li>💻 Laptop (50Wh): ±47 kali charge</li>
<li>🍚 Rice Cooker (400W): ±5,8 jam</li>
<li>💡 Lampu (10W): ±235 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI Apex 300</td></tr>
<tr><th>Kapasitas</th><td>2.764,8 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 grade otomotif (6.000+ siklus ke 80%, umur pakai ±17 tahun)</td></tr>
<tr><th>Output AC</th><td>3840W total, 230V / 16,7A / 50–60Hz, Pure Sine Wave (lonjakan s/d 7680W)</td></tr>
<tr><th>Stopkontak AC</th><td>6 stopkontak AC</td></tr>
<tr><th>Output DC / USB</th><td>Via aksesori opsional Power Hub D1: 2× USB-C 100W, 2× USB-A, 2× port mobil 12V, 12V/50A Anderson</td></tr>
<tr><th>Input AC (Pengisian)</th><td>TurboBoost hingga 3840W — 0–100% ±65 menit</td></tr>
<tr><th>Input Surya (PV)</th><td>Hingga 1200W (12–150V, MC4); hingga 4000W dengan SolarX 4K</td></tr>
<tr><th>Ekspansi</th><td>Hingga ±19,3 kWh per unit (maks 6 baterai ekspansi B300K, masing-masing 2.764,8Wh); hingga 58 kWh &amp; 11,52kW dengan 3 unit paralel (Hub A1)</td></tr>
<tr><th>Siklus Hidup</th><td>6.000+ siklus (ke 80%)</td></tr>
<tr><th>Berat</th><td>±38,6 kg (85 lbs)</td></tr>
<tr><th>Dimensi</th><td>525 × 327 × 320 mm</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-apex-300'],
            [
                'sku' => 'BLUETTI-APEX300',   // Reseller SKU: P-APEX300-EU-GY-BL-010
                'name' => 'BLUETTI Apex 300 Portable Power Station (2764.8Wh / 3840W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'Apex 300',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station modular BLUETTI Apex 300, 2.764,8Wh LiFePO4, output 3840W Pure Sine Wave (lonjakan 7680W), 230V/50Hz. Ekspandabel hingga 58 kWh. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 35889000,        // Harga coret
                'sale_price' => 32299050,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 38600,
                'length_cm' => 52.5,
                'width_cm' => 32.7,
                'height_cm' => 32,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI Apex 300 2764.8Wh 3840W — Power Station Modular LiFePO4',
                'meta_description' => 'Jual BLUETTI Apex 300 power station modular 2.764,8Wh / 3840W LiFePO4, Pure Sine Wave, ekspandabel s/d 58 kWh. Rp 32.299.050 (dari Rp 35.889.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 1);
        }

        $this->command?->info('Produk BLUETTI Apex 300 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 35.889.000 → Rp 32.299.050 • Stok awal 1 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
