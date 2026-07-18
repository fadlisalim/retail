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
 * Seeds the BLUETTI EB3A portable power station (268Wh / 600W LiFePO4).
 * Struck price Rp 3.699.000 → sale Rp 3.329.100, stok 3. Specs sourced from the
 * official BLUETTI datasheet (bluettipower.com / EB3A user manual) + distributor
 * marketing sheets. Versi 230V (pasar Indonesia). Idempotent (firstOrCreate).
 * Images uploaded via admin.
 */
class BluettiEb3aSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI EB3A — Power Station Portabel 268Wh / 600W</strong><br>Solusi power ringkas untuk aktivitas harian &amp; petualangan singkat. Baterai <strong>LiFePO4 268Wh</strong> dengan output <strong>600W Pure Sine Wave</strong> (Power Lifting hingga 1200W), namun super ringan dengan bobot hanya <strong>4,6 kg</strong>. Ideal untuk kerja remote, camping, backup listrik darurat, hingga charging perangkat saat mati lampu.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 268Wh</strong> — 2.500+ siklus pengisian, jauh lebih awet dari baterai lithium biasa.</li>
<li>⚡ <strong>Output 600W</strong> (Power Lifting hingga 1200W) — kuat untuk laptop, TV, kipas, lampu, hingga peralatan kecil.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih &amp; stabil seperti listrik rumah, aman untuk laptop &amp; gadget sensitif.</li>
<li>🪶 <strong>Sangat ringan &amp; portabel</strong> — hanya 4,6 kg dengan handle terintegrasi, mudah dibawa ke mana saja.</li>
<li>⏱️ <strong>Pengisian super cepat</strong> — 0–80% hanya ±30 menit, penuh ±1,3–1,8 jam via AC (Turbo).</li>
<li>🔌 <strong>Banyak port</strong> — AC 230V, USB-C 100W, 2× USB-A, DC mobil 12V/10A, 2× DC 5521, plus pad wireless charging 15W.</li>
<li>☀️ <strong>Isi daya fleksibel</strong> — AC rumah, panel surya hingga 200W, mobil, atau generator.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil — dilengkapi kontrol pintar via aplikasi BLUETTI.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan)</small></h4>
<ul>
<li>📱 Smartphone (10Wh): ±20 kali charge</li>
<li>💻 Laptop (50Wh): ±4 kali charge</li>
<li>📺 TV 32" (60W): ±3,5 jam</li>
<li>🌀 Kipas Angin (20W): ±10 jam</li>
<li>💡 Lampu (10W): ±20 jam</li>
<li>🧊 Mini Cooler (45W): ±4,5 jam</li>
</ul>
<p><em>Garansi Resmi 2 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI EB3A</td></tr>
<tr><th>SKU Distributor</th><td>P-EB3A-EU-GY-BL-010</td></tr>
<tr><th>Kapasitas</th><td>268,8 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (2.500+ siklus, ke 80%)</td></tr>
<tr><th>Output AC</th><td>230V, total 600W, Pure Sine Wave (Power Lifting s/d 1200W)</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>USB-C</th><td>100W maks (1 port)</td></tr>
<tr><th>USB-A</th><td>5V/3A — 2 port</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A</td></tr>
<tr><th>Output DC 5521</th><td>12V / 10A — 2 port</td></tr>
<tr><th>Wireless Charging</th><td>15W (pad di bagian atas)</td></tr>
<tr><th>Input AC (Pengisian)</th><td>350W maks (Turbo) — penuh ±1,3–1,8 jam, 0–80% ±30 menit</td></tr>
<tr><th>Input Surya (PV)</th><td>200W maks, 12–28V / 8.5A</td></tr>
<tr><th>Siklus Hidup</th><td>2.500+ siklus (ke 80%)</td></tr>
<tr><th>Berat</th><td>4,6 kg</td></tr>
<tr><th>Dimensi</th><td>255 × 180 × 183 mm</td></tr>
<tr><th>Garansi</th><td>Resmi 2 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-eb3a'],
            [
                'sku' => 'BLUETTI-EB3A',
                'name' => 'BLUETTI EB3A Portable Power Station (268Wh / 600W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'EB3A',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI EB3A, 268Wh LiFePO4, output 600W Pure Sine Wave (Power Lifting 1200W). Bobot hanya 4,6 kg, isi 0–80% ±30 menit. Garansi resmi 2 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 3699000,        // Harga coret
                'sale_price' => 3329100,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 4600,
                'length_cm' => 25.5,
                'width_cm' => 18,
                'height_cm' => 18.3,
                'warranty' => 'Garansi Resmi 2 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI EB3A 268Wh 600W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI EB3A power station portabel 268Wh / 600W LiFePO4, Pure Sine Wave. Rp 3.329.100 (dari Rp 3.699.000). Garansi resmi 2 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 3);
        }

        $this->command?->info('Produk BLUETTI EB3A '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 3.699.000 → Rp 3.329.100 • Stok awal 3 • Garansi 2 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
