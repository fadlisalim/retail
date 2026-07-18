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
 * Seeds the BLUETTI AC180P portable power station (1440Wh / 1800W LiFePO4).
 * Struck price Rp 16.669.000 → sale Rp 14.999.000, stok 2. Specs sourced from the
 * official BLUETTI datasheet (bluettipower.com / user manual, EU 230V model) +
 * distributor marketing sheets. Idempotent (firstOrCreate). Images uploaded via admin.
 */
class BluettiAc180pSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI AC180P — Power Station Portabel 1440Wh / 1800W</strong><br>Kapasitas besar untuk kebutuhan daya yang lebih berat. Baterai <strong>LiFePO4 premium 1440Wh</strong> dengan output <strong>1800W Pure Sine Wave</strong> (Power Lifting hingga 2700W), namun tetap mudah dibawa dengan bobot <strong>16 kg</strong>. Ideal untuk backup listrik rumah, camping, caravan, road trip, hingga kebutuhan outdoor profesional.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 1440Wh</strong> — 3.500+ siklus pengisian, umur pakai panjang &amp; tahan lama.</li>
<li>⚡ <strong>Output 1800W</strong> (Power Lifting hingga 2700W) — kuat untuk kulkas, microwave, coffee maker, bor listrik, hingga peralatan dapur.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih &amp; stabil seperti listrik rumah, aman untuk laptop &amp; gadget sensitif.</li>
<li>🚀 <strong>Turbo Charging</strong> — input AC hingga 1440W, isi 0–80% hanya ±45 menit.</li>
<li>🔌 <strong>Banyak port (11 output)</strong> — 2× AC 230V, USB-C 100W, 4× USB-A, DC mobil 12V/10A, plus wireless charging pad 15W.</li>
<li>☀️ <strong>Isi daya fleksibel</strong> — AC rumah, surya hingga 500W (MPPT), mobil, atau generator.</li>
<li>📱 <strong>Kontrol via App</strong> — pantau &amp; atur daya dari smartphone lewat Bluetooth.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil di suhu tinggi.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan)</small></h4>
<ul>
<li>💻 Laptop (50Wh): ±24 kali charge</li>
<li>📺 TV 32" (60W): ±20 jam</li>
<li>🧊 Kulkas Mini (45W): ±27 jam</li>
<li>🌀 Kipas Angin (20W): ±60 jam</li>
<li>💡 Lampu (10W): ±120 jam</li>
<li>☕ Coffee Maker (800W): ±1,5 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI AC180P</td></tr>
<tr><th>Kapasitas</th><td>1440 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (3.500+ siklus, ke 80%)</td></tr>
<tr><th>Output AC</th><td>2× 230V / 50Hz, total 1800W, Pure Sine Wave (Power Lifting s/d 2700W)</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A</td></tr>
<tr><th>USB-C</th><td>100W maks — 1 port</td></tr>
<tr><th>USB-A</th><td>5V / 3A (15W) — 4 port</td></tr>
<tr><th>Wireless Charging</th><td>15W maks</td></tr>
<tr><th>Input AC (Pengisian)</th><td>1440W maks (Turbo) — 0–80% ±45 menit</td></tr>
<tr><th>Input Surya (PV)</th><td>500W maks, 12–60V / 10A (MPPT)</td></tr>
<tr><th>Baterai Ekspansi</th><td>Tidak mendukung ekspansi kapasitas (dapat diisi via Power Bank Mode dari B80/B230/B300)</td></tr>
<tr><th>Siklus</th><td>3.500+ siklus (ke 80%)</td></tr>
<tr><th>Berat</th><td>16 kg</td></tr>
<tr><th>Dimensi</th><td>340 × 247 × 317 mm</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-ac180p'],
            [
                'sku' => 'BLUETTI-AC180P',
                'name' => 'BLUETTI AC180P Portable Power Station (1440Wh / 1800W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'AC180P',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI AC180P, 1440Wh LiFePO4, output 1800W Pure Sine Wave (Power Lifting 2700W). Turbo charging 0–80% ±45 menit. Bobot 16 kg. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 16669000,       // Harga coret
                'sale_price' => 14999000,  // Harga jual
                'unit' => 'unit',
                'weight_grams' => 16000,
                'length_cm' => 34,
                'width_cm' => 24.7,
                'height_cm' => 31.7,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI AC180P 1440Wh 1800W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI AC180P power station portabel 1440Wh / 1800W LiFePO4, Pure Sine Wave (Power Lifting 2700W). Rp 14.999.000 (dari Rp 16.669.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 2);
        }

        $this->command?->info('Produk BLUETTI AC180P '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 16.669.000 → Rp 14.999.000 • Stok awal 2 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
