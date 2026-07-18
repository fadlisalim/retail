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
 * Seeds the BLUETTI "Premium 30 V2" portable power station (288Wh / 600W LiFePO4).
 * Reseller tier "Premium 30 V2" (distributor SKU P-PR30V2-EU-GY-BL-010) = the EU-market
 * BLUETTI Elite 30 V2 (2025). Struck price Rp 6.109.000 → sale Rp 5.499.970, stok 4.
 * Specs sourced from the official BLUETTI product page (bluettipower.com /
 * support.bluettipower.com) + reputable reviews. Idempotent (firstOrCreate).
 * Images uploaded via admin.
 */
class BluettiPremium30V2Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI Premium 30 V2 — Power Station Portabel 288Wh / 600W</strong><br>Power ringkas yang siap menemani aktivitas harian &amp; petualangan Anda. Baterai <strong>LiFePO4 premium 288Wh</strong> dengan output <strong>600W Pure Sine Wave</strong> (Power Lifting hingga 1500W), namun ultra-portabel dengan bobot hanya <strong>4,3 kg</strong>. Cocok untuk camping, kerja outdoor, glamping, hingga backup listrik darurat berkat UPS ≤10ms.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 288Wh</strong> — 3.000+ siklus pengisian, umur pakai hingga 10 tahun.</li>
<li>⚡ <strong>Output 600W</strong> (Power Lifting hingga 1500W) — kuat untuk laptop, TV, kipas, mini kulkas, hingga peralatan kecil.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih &amp; stabil seperti listrik rumah, aman untuk laptop &amp; gadget.</li>
<li>🪶 <strong>Ultra-portabel</strong> — hanya 4,3 kg dengan handle ergonomis, mudah dibawa ke mana saja.</li>
<li>🔌 <strong>9 port</strong> — 2× AC, USB-C 140W (PD 3.1), USB-C 100W, 2× USB-A 15W, port mobil 12V/10A, 2× DC5521 12V.</li>
<li>🔋 <strong>UPS ≤10ms</strong> — otomatis backup saat listrik padam, aman untuk perangkat penting.</li>
<li>⚡ <strong>Pengisian cepat TurboBoost</strong> — 0–80% hanya ±45 menit lewat AC (input maks 380W).</li>
<li>☀️ <strong>Isi daya surya</strong> — hingga 200W (12–28V / 10A), penuh dari surya ±2,2 jam.</li>
<li>🔇 <strong>Senyap &amp; hemat</strong> — noise di bawah 30dB, standby hanya 4,5W (UltraCell™).</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil di suhu tinggi.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan)</small></h4>
<ul>
<li>📱 Smartphone (10Wh): ±20 kali charge</li>
<li>💻 Laptop (50Wh): ±4–5 kali charge</li>
<li>📺 TV 32" (60W): ±4 jam</li>
<li>🧊 Mini Kulkas (45W): ±5 jam</li>
<li>🌀 Kipas Angin (20W): ±12 jam</li>
<li>💡 Lampu LED (10W): ±24 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>Premium 30 V2 (BLUETTI Elite 30 V2)</td></tr>
<tr><th>Kapasitas</th><td>288 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (3.000+ siklus)</td></tr>
<tr><th>Output AC</th><td>2× outlet, total 600W, Pure Sine Wave (Power Lifting s/d 1500W)</td></tr>
<tr><th>USB-C</th><td>1× 140W (PD 3.1) + 1× 100W</td></tr>
<tr><th>USB-A</th><td>2× 15W</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A</td></tr>
<tr><th>Port DC</th><td>2× DC5521 (12V)</td></tr>
<tr><th>Input AC (Pengisian)</th><td>380W maks — 0–80% ±45 menit</td></tr>
<tr><th>Input Surya (PV)</th><td>200W maks, 12–28V / 10A — penuh ±2,2 jam</td></tr>
<tr><th>UPS</th><td>Switchover ≤10 ms</td></tr>
<tr><th>Kebisingan</th><td>Di bawah 30 dB</td></tr>
<tr><th>Daya Standby</th><td>4,5W (UltraCell™)</td></tr>
<tr><th>Konektivitas</th><td>WiFi / Bluetooth (BLUETTI App)</td></tr>
<tr><th>Umur Pakai</th><td>Hingga 10 tahun</td></tr>
<tr><th>Berat</th><td>4,3 kg</td></tr>
<tr><th>Dimensi</th><td>250 × 178 × 167,5 mm</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-premium-30-v2'],
            [
                'sku' => 'BLUETTI-PR30V2',
                'name' => 'BLUETTI Premium 30 V2 Portable Power Station (288Wh / 600W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'Premium 30 V2',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI Premium 30 V2, 288Wh LiFePO4, output 600W Pure Sine Wave (Power Lifting 1500W). UPS ≤10ms, USB-C 140W. Bobot 4,3 kg. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 6109000,        // Harga coret
                'sale_price' => 5499970,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 4300,
                'length_cm' => 25,
                'width_cm' => 17.8,
                'height_cm' => 16.75,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI Premium 30 V2 288Wh 600W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI Premium 30 V2 power station portabel 288Wh / 600W LiFePO4, Pure Sine Wave, UPS ≤10ms. Rp 5.499.970 (dari Rp 6.109.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 4);
        }

        $this->command?->info('Produk BLUETTI Premium 30 V2 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 6.109.000 → Rp 5.499.970 • Stok awal 4 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
