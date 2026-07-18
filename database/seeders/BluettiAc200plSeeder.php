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
 * Seeds the BLUETTI AC200PL portable power station (2304Wh / 2400W LiFePO4,
 * expandable to 8448Wh). Struck price Rp 24.999.000 → sale Rp 22.499.900,
 * stok 2. Specs sourced from the official BLUETTI datasheet
 * (bluettipower.com / bluettipower.eu — AC200PL, varian EU) plus reseller
 * spec sheets (Amazon/Wellbots/MyGenerator). Idempotent (firstOrCreate).
 * Images uploaded via admin.
 */
class BluettiAc200plSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI AC200PL — Power Station Portabel 2304Wh / 2400W</strong><br>Sumber listrik bertenaga besar untuk rumah, RV, hingga off-grid. Baterai <strong>LiFePO4 premium 2304Wh</strong> dengan inverter <strong>2400W Pure Sine Wave</strong> (Power Lifting hingga 3600W), dan dapat diperluas hingga <strong>8448Wh</strong> lewat baterai ekspansi. Pengisian Turbo super cepat, mendukung tenaga surya 1200W, serta lengkap dengan 4 stopkontak AC, wireless charging, dan port RV.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 2304Wh</strong> — 3.000+ siklus pengisian (ke 80%), umur pakai hingga 10 tahun.</li>
<li>⚡ <strong>Output 2400W</strong> (Power Lifting hingga 3600W) — sanggup menyalakan kulkas, pompa air, microwave, peralatan dapur, hingga perkakas listrik.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih &amp; stabil seperti listrik PLN, aman untuk elektronik sensitif.</li>
<li>🔗 <strong>Kapasitas dapat diperluas hingga 8448Wh</strong> — kompatibel dengan baterai ekspansi B210P / B230 / B300.</li>
<li>🔌 <strong>Banyak port</strong> — 4× AC 230V, 1× DC mobil 12V/10A, 1× DC 48V/8A (RV), 2× USB-C 100W, 2× USB-A 18W, 2× wireless charging 15W.</li>
<li>🚀 <strong>Turbo Charging 2400W</strong> — isi penuh hanya ±1,5 jam (80% dalam ±1 jam).</li>
<li>☀️ <strong>Input surya hingga 1200W</strong> — OCV 12–145V, konektor MC4, ideal untuk solar generator.</li>
<li>📱 <strong>Kontrol via BLUETTI App</strong> — pantau &amp; atur lewat Bluetooth/Wi-Fi; tingkat kebisingan hening ≤50dB.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil di suhu tinggi.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan)</small></h4>
<ul>
<li>🧊 Kulkas (150W): ±11 jam</li>
<li>💻 Laptop (50Wh): ±37 kali charge</li>
<li>📺 TV 32" (60W): ±30 jam</li>
<li>🌀 Kipas Angin (20W): ±90 jam</li>
<li>💡 Lampu (10W): ±180 jam</li>
<li>☕ Coffee Maker (800W): ±2,3 jam</li>
<li>🔥 Microwave (1000W): ±1,8 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI AC200PL</td></tr>
<tr><th>Kapasitas</th><td>2304 Wh (dapat diperluas hingga 8448 Wh)</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (3.000+ siklus, ke 80%) — umur pakai hingga 10 tahun</td></tr>
<tr><th>Output AC</th><td>4× 230V, total 2400W, Pure Sine Wave (Power Lifting s/d 3600W)</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A (teregulasi)</td></tr>
<tr><th>Output DC (RV)</th><td>48V / 8A</td></tr>
<tr><th>USB-C</th><td>2× 100W maks</td></tr>
<tr><th>USB-A</th><td>2× 18W</td></tr>
<tr><th>Wireless Charging</th><td>2× 15W</td></tr>
<tr><th>Input AC (Pengisian)</th><td>2400W maks (Turbo) — penuh ±1,5 jam, 80% dalam ±1 jam</td></tr>
<tr><th>Input Surya (PV)</th><td>1200W maks, OCV 12–145V, konektor MC4</td></tr>
<tr><th>Baterai Ekspansi</th><td>Mendukung B210P / B230 / B300 — total maks 8448 Wh</td></tr>
<tr><th>Siklus Baterai</th><td>3.000+ siklus (ke 80%)</td></tr>
<tr><th>Tingkat Kebisingan</th><td>≤50dB</td></tr>
<tr><th>Berat</th><td>28,8 kg</td></tr>
<tr><th>Dimensi</th><td>420 × 280 × 366,5 mm</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-ac200pl'],
            [
                'sku' => 'BLUETTI-AC200PL',
                'name' => 'BLUETTI AC200PL Portable Power Station (2304Wh / 2400W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'AC200PL',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI AC200PL, 2304Wh LiFePO4 (perluasan s/d 8448Wh), output 2400W Pure Sine Wave (Power Lifting 3600W), Turbo Charging 2400W, surya 1200W. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 24999000,        // Harga coret
                'sale_price' => 22499900,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 28800,
                'length_cm' => 42,
                'width_cm' => 28,
                'height_cm' => 36.65,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI AC200PL 2304Wh 2400W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI AC200PL power station portabel 2304Wh / 2400W LiFePO4, Pure Sine Wave, perluasan s/d 8448Wh. Rp 22.499.900 (dari Rp 24.999.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 2);
        }

        $this->command?->info('Produk BLUETTI AC200PL '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 24.999.000 → Rp 22.499.900 • Stok awal 2 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
