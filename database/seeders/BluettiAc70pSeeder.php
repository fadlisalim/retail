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
 * Seeds the BLUETTI AC70P portable power station (864Wh / 1000W LiFePO4).
 * Struck price Rp 9.219.000 → sale Rp 8.299.000, stok 5. Specs sourced from the
 * official BLUETTI datasheet (bluettipower.com / user manual) + distributor
 * marketing sheets. Idempotent (firstOrCreate). Images uploaded via admin.
 */
class BluettiAc70pSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI AC70P — Power Station Portabel 864Wh / 1000W</strong><br>Power maksimal untuk menemani petualangan Anda. Baterai <strong>LiFePO4 premium 864Wh</strong> dengan output <strong>1000W Pure Sine Wave</strong> (Power Lifting hingga 2000W), namun tetap ringkas dengan bobot hanya <strong>10,7 kg</strong>. Cocok untuk camping, caravan, road trip, outdoor event, hingga backup listrik darurat.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 864Wh</strong> — 3.000+ siklus pengisian, umur pakai hingga 10 tahun.</li>
<li>⚡ <strong>Output 1000W</strong> (Power Lifting hingga 2000W) — kuat untuk laptop, TV, kipas, mini cooler, hingga coffee maker.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih &amp; stabil seperti listrik rumah, aman untuk laptop &amp; gadget.</li>
<li>🪶 <strong>Ringkas &amp; portabel</strong> — 10,7 kg dengan handle ergonomis, mudah dibawa ke mana saja.</li>
<li>🔌 <strong>Banyak port</strong> — 2× AC 230V, 1× DC mobil 12V/10A, USB-C 100W, 2× USB-A 12W.</li>
<li>☀️ <strong>Isi daya fleksibel</strong> — AC rumah (950W, penuh ±1,5 jam Turbo), surya hingga 500W, mobil, atau generator.</li>
<li>🔗 <strong>Dukungan baterai ekspansi</strong> — kompatibel dengan B80P / B230 / B300 untuk kapasitas lebih besar.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, tahan panas &amp; stabil di suhu tinggi.</li>
</ul>
<h4>Estimasi Waktu Pakai <small>(*perkiraan, tergantung penggunaan)</small></h4>
<ul>
<li>💻 Laptop (50Wh): ±14 kali charge</li>
<li>📺 TV 32" (60W): ±12 jam</li>
<li>🧊 Mini Cooler (45W): ±18 jam</li>
<li>🌀 Kipas Angin (20W): ±40 jam</li>
<li>💡 Lampu (10W): ±80 jam</li>
<li>☕ Coffee Maker (800W): ±1,2 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI AC70P</td></tr>
<tr><th>Kapasitas</th><td>864 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (3.000+ siklus, ke 80%)</td></tr>
<tr><th>Output AC</th><td>2× 230V, total 1000W, Pure Sine Wave (Power Lifting s/d 2000W)</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A</td></tr>
<tr><th>USB-C</th><td>100W maks</td></tr>
<tr><th>USB-A</th><td>12W (5V/2.4A) — 2 port</td></tr>
<tr><th>Input AC (Pengisian)</th><td>950W maks — penuh ±1,5 jam (Turbo)</td></tr>
<tr><th>Input Surya (PV)</th><td>500W maks, 12–58V / 10A</td></tr>
<tr><th>Baterai Ekspansi</th><td>Mendukung B80P / B230 / B300</td></tr>
<tr><th>Umur Pakai</th><td>Hingga 10 tahun</td></tr>
<tr><th>Berat</th><td>10,7 kg</td></tr>
<tr><th>Dimensi</th><td>314 × 209,5 × 255,8 mm</td></tr>
<tr><th>Gelombang</th><td>Pure Sine Wave</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-ac70p'],
            [
                'sku' => 'BLUETTI-AC70P',
                'name' => 'BLUETTI AC70P Portable Power Station (864Wh / 1000W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'AC70P',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI AC70P, 864Wh LiFePO4, output 1000W Pure Sine Wave (Power Lifting 2000W). Bobot 10,7 kg. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 9219000,        // Harga coret
                'sale_price' => 8299000,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 10700,
                'length_cm' => 31.4,
                'width_cm' => 21,
                'height_cm' => 25.6,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => true,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI AC70P 864Wh 1000W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI AC70P power station portabel 864Wh / 1000W LiFePO4, Pure Sine Wave. Rp 8.299.000 (dari Rp 9.219.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 5);
        }

        $this->command?->info('Produk BLUETTI AC70P '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 9.219.000 → Rp 8.299.000 • Stok awal 5 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
