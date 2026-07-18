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
 * Seeds the BLUETTI Elite 100 V2 portable power station (1024Wh / 1800W LiFePO4).
 * Reseller tier "Premium 100 V2" maps to the official BLUETTI Elite 100 V2 (2024/2025 model).
 * Struck price Rp 12.669.000 → sale Rp 11.399.000, stok 2. Specs sourced from the official
 * BLUETTI datasheet (bluettipower.com / support.bluettipower.com) + reputable review sheets.
 * Idempotent (firstOrCreate). Images uploaded via admin.
 */
class BluettiElite100V2Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI Elite 100 V2 — Power Station Portabel 1024Wh / 1800W</strong><br>Power besar dalam bodi ringkas. Baterai <strong>LiFePO4 premium 1024Wh</strong> dengan output <strong>1800W Pure Sine Wave</strong> (Power Lifting hingga 2700W, surge 3600W), namun <strong>35% lebih kecil</strong> dari generasi sebelumnya dan bobot hanya <strong>11,5 kg</strong>. Cocok untuk camping, caravan, road trip, work from anywhere, hingga backup listrik darurat rumah.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 1024Wh</strong> — 4.000+ siklus pengisian, umur pakai hingga 10 tahun.</li>
<li>⚡ <strong>Output 1800W</strong> (Power Lifting hingga 2700W, surge 3600W) — kuat untuk laptop, TV, kulkas mini, bor, hingga coffee maker.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih &amp; stabil seperti listrik rumah, aman untuk laptop &amp; gadget sensitif.</li>
<li>🔌 <strong>11 port sekaligus</strong> — 4× AC 230V, 2× USB-C (140W &amp; 100W), 2× USB-A, DC mobil 12V/10A, 2× DC5521.</li>
<li>⚡ <strong>TurboBoost™ pengisian super cepat</strong> — 0–80% hanya ±45 menit, penuh ±70 menit (AC 1200W).</li>
<li>☀️ <strong>Input surya hingga 1000W</strong> — MPPT bawaan, penuh ±70 menit dalam kondisi ideal.</li>
<li>🔌 <strong>UPS 10ms</strong> — pindah daya nyaris instan, aman untuk PC &amp; perangkat kritis saat mati listrik.</li>
<li>🤫 <strong>Senyap</strong> — hanya ±30dB pada beban di bawah 600W.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil di suhu tinggi.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan)</small></h4>
<ul>
<li>💻 Laptop (50Wh): ±17 kali charge</li>
<li>📺 TV 32" (60W): ±14 jam</li>
<li>🧊 Kulkas Mini (45W): ±19 jam</li>
<li>🌀 Kipas Angin (20W): ±43 jam</li>
<li>💡 Lampu (10W): ±87 jam</li>
<li>☕ Coffee Maker (800W): ±1 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI Elite 100 V2</td></tr>
<tr><th>Kapasitas</th><td>1024 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (4.000+ siklus, ke 80%)</td></tr>
<tr><th>Output AC</th><td>4× 230V, total 1800W, Pure Sine Wave (Power Lifting s/d 2700W, Surge 3600W)</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A</td></tr>
<tr><th>Output DC5521</th><td>2× 12V / 5A (maks. total 8A)</td></tr>
<tr><th>USB-C</th><td>2 port — 1× 140W, 1× 100W</td></tr>
<tr><th>USB-A</th><td>2 port — 15W (5V/3A)</td></tr>
<tr><th>Input AC (Pengisian)</th><td>1200W maks — 0–80% ±45 menit, penuh ±70 menit (TurboBoost)</td></tr>
<tr><th>Input Surya (PV)</th><td>1000W maks, 12–60V / 20A (MPPT)</td></tr>
<tr><th>UPS</th><td>Switchover 10ms</td></tr>
<tr><th>Umur Pakai</th><td>Hingga 10 tahun</td></tr>
<tr><th>Berat</th><td>11,5 kg</td></tr>
<tr><th>Dimensi</th><td>320 × 215 × 250 mm</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-elite-100-v2'],
            [
                'sku' => 'BLUETTI-ELITE100V2',
                'name' => 'BLUETTI Elite 100 V2 Portable Power Station (1024Wh / 1800W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'Elite 100 V2',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI Elite 100 V2, 1024Wh LiFePO4, output 1800W Pure Sine Wave (Power Lifting 2700W, surge 3600W). TurboBoost 0–80% ±45 menit. Bobot 11,5 kg. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 12669000,        // Harga coret
                'sale_price' => 11399000,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 11500,
                'length_cm' => 32,
                'width_cm' => 21.5,
                'height_cm' => 25,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI Elite 100 V2 1024Wh 1800W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI Elite 100 V2 power station portabel 1024Wh / 1800W LiFePO4, Pure Sine Wave. Rp 11.399.000 (dari Rp 12.669.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 2);
        }

        $this->command?->info('Produk BLUETTI Elite 100 V2 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 12.669.000 → Rp 11.399.000 • Stok awal 2 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
