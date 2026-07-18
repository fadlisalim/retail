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
 * Seeds the BLUETTI Elite 200 V2 portable power station (2073.6Wh / 2600W LiFePO4).
 * Reseller tier "Premium 200 V2" = BLUETTI Elite 200 V2 (2024/2025 model).
 * Struck price Rp 22.219.000 → sale Rp 19.999.900, stok 1. Specs sourced from the
 * official BLUETTI datasheet (bluettipower.com / support.bluettipower.com / user
 * manual). Idempotent (firstOrCreate). Images uploaded via admin.
 */
class BluettiElite200V2Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI Elite 200 V2 — Power Station Portabel 2073.6Wh / 2600W</strong><br>Tenaga besar untuk backup rumah, kerja, hingga petualangan. Baterai <strong>LiFePO4 premium 2073,6Wh</strong> dengan output <strong>2600W Pure Sine Wave</strong> (Power Lifting hingga 3900W) mampu menghidupkan hampir seluruh perangkat rumah tangga. Pengisian super cepat <strong>0–80% dalam 50 menit</strong> dan hingga <strong>9 perangkat sekaligus</strong>. Ideal untuk backup listrik darurat, home office, RV/caravan, camping, hingga acara outdoor.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 2073,6Wh</strong> — 6.000+ siklus pengisian (ke 80%), umur pakai hingga 17 tahun.</li>
<li>⚡ <strong>Output 2600W</strong> (Power Lifting hingga 3900W) — kuat untuk kulkas, AC portabel, mesin cuci, microwave, hingga peralatan dapur.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih &amp; stabil seperti listrik rumah, aman untuk laptop, gadget, &amp; alat sensitif.</li>
<li>🔌 <strong>9 output</strong> — 4× AC 230V, 2× USB-C 100W, 2× USB-A 15W, 1× DC mobil 12V/10A.</li>
<li>🚀 <strong>Pengisian super cepat</strong> — 0–80% hanya 50 menit (AC + DC), penuh ±1,6 jam via AC 1440W.</li>
<li>☀️ <strong>Input surya hingga 1000W</strong> — MPPT bawaan, 12–60V / 20A, penuh ±2,4 jam dengan panel surya.</li>
<li>🔗 <strong>Dukungan baterai ekspansi</strong> — kompatibel dengan B300 untuk kapasitas jauh lebih besar.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil di suhu tinggi.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan)</small></h4>
<ul>
<li>🧊 Kulkas (150W): ±11 jam</li>
<li>💻 Laptop (50Wh): ±35 kali charge</li>
<li>📺 TV 32" (60W): ±29 jam</li>
<li>🌀 Kipas Angin (20W): ±90 jam</li>
<li>💡 Lampu (10W): ±180 jam</li>
<li>☕ Coffee Maker (800W): ±2,3 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI Elite 200 V2</td></tr>
<tr><th>Kapasitas</th><td>2073,6 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (6.000+ siklus, ke 80%)</td></tr>
<tr><th>Output AC</th><td>4× 230V, total 2600W, Pure Sine Wave (Power Lifting s/d 3900W)</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A</td></tr>
<tr><th>USB-C</th><td>100W maks — 2 port</td></tr>
<tr><th>USB-A</th><td>15W (5V/3A) — 2 port</td></tr>
<tr><th>Input AC (Pengisian)</th><td>1440W maks — penuh ±1,6 jam; 0–80% ±50 menit (AC + DC)</td></tr>
<tr><th>Input Surya (PV)</th><td>1000W maks, 12–60V / 20A (MPPT) — penuh ±2,4 jam</td></tr>
<tr><th>Baterai Ekspansi</th><td>Mendukung B300</td></tr>
<tr><th>Umur Pakai</th><td>Hingga 17 tahun</td></tr>
<tr><th>Berat</th><td>24,2 kg</td></tr>
<tr><th>Dimensi</th><td>350 × 250 × 323,6 mm</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-elite-200-v2'],
            [
                'sku' => 'BLUETTI-ELITE200V2',
                'name' => 'BLUETTI Elite 200 V2 Portable Power Station (2073.6Wh / 2600W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'Elite 200 V2',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI Elite 200 V2, 2073,6Wh LiFePO4, output 2600W Pure Sine Wave (Power Lifting 3900W). Isi 0–80% dalam 50 menit. Bobot 24,2 kg. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 22219000,       // Harga coret
                'sale_price' => 19999900,  // Harga jual
                'unit' => 'unit',
                'weight_grams' => 24200,
                'length_cm' => 35,
                'width_cm' => 25,
                'height_cm' => 32.36,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI Elite 200 V2 2073.6Wh 2600W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI Elite 200 V2 power station portabel 2073,6Wh / 2600W LiFePO4, Pure Sine Wave. Rp 19.999.900 (dari Rp 22.219.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 1);
        }

        $this->command?->info('Produk BLUETTI Elite 200 V2 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 22.219.000 → Rp 19.999.900 • Stok awal 1 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
