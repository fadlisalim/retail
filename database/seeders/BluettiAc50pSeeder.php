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
 * Seeds the BLUETTI AC50P portable power station (504Wh / 700W LiFePO4).
 * Struck price Rp 9.219.000 → sale Rp 8.299.000. Specs sourced from the
 * official BLUETTI datasheet + the distributor (FRG) marketing sheets.
 * Idempotent. Images uploaded via admin.
 */
class BluettiAc50pSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI AC50P — Power Station Portabel 504Wh / 700W</strong><br>Tetap punya listrik di mana pun Anda berada. Power station ringkas dengan baterai <strong>LiFePO4</strong> premium, output <strong>700W</strong> (Pure Sine Wave), dan bobot hanya <strong>6,9 kg</strong> — mudah dibawa untuk camping, road trip, outdoor event, hingga backup listrik darurat.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Baterai LiFePO4 504Wh</strong> — 3.000+ siklus pengisian, umur pakai hingga 10 tahun.</li>
<li>⚡ <strong>Output 700W</strong> (Power Lifting hingga 1.200W) — kuat untuk rice cooker, kipas, laptop, hingga peralatan kecil.</li>
<li>🪶 <strong>Ringan &amp; portabel</strong> — hanya 6,9 kg, dimensi ringkas 28 × 20 × 22 cm.</li>
<li>🔌 <strong>Banyak port</strong> — 2× AC 230V, 1× DC mobil 12V/10A, 1× USB-C 65W, 2× USB-A 15W.</li>
<li>☀️ <strong>Berbagai cara isi daya</strong> — Surya (200W), AC rumah, mobil, atau generator.</li>
<li>🛡️ <strong>BMS Protection</strong> — proteksi over-voltage, over-current, over-heat &amp; short-circuit.</li>
<li>🤫 Mode ECO senyap (~45 dB), suhu operasi −20°C s/d 40°C.</li>
</ul>
<h4>Estimasi Waktu Pengisian</h4>
<ul>
<li>🔌 AC (700W): <strong>±1–2 jam</strong> (Turbo: 80% dalam ~50 menit)</li>
<li>☀️ Surya 200W: ±3–4 jam (dengan 1× panel 200W)</li>
<li>🚗 Mobil 12V/24V: ±6–7 jam</li>
<li>🔋 Generator (700W): ±1–2 jam</li>
</ul>
<p><em>Garansi Resmi 5 Tahun. Distributor resmi: PT Famindo Roda Gemilang.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI AC50P</td></tr>
<tr><th>Kapasitas</th><td>504 Wh</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 (3.000+ siklus)</td></tr>
<tr><th>Output AC</th><td>230V / 700W, Pure Sine Wave (Power Lifting s/d 1.200W)</td></tr>
<tr><th>Output DC (Mobil)</th><td>12V / 10A</td></tr>
<tr><th>USB-C</th><td>65W (1 port)</td></tr>
<tr><th>USB-A</th><td>15W (2 port)</td></tr>
<tr><th>Input Surya (PV)</th><td>12–28V / 8A / 200W maks</td></tr>
<tr><th>Umur Pakai</th><td>Hingga 10 tahun</td></tr>
<tr><th>Berat</th><td>6,9 kg</td></tr>
<tr><th>Dimensi</th><td>280 × 200 × 220 mm</td></tr>
<tr><th>Suhu Operasi</th><td>−20°C – 40°C</td></tr>
<tr><th>Kebisingan</th><td>~45 dB (mode ECO)</td></tr>
<tr><th>Proteksi</th><td>BMS: over-voltage, over-current, over-heat, short-circuit</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::updateOrCreate(
            ['slug' => 'bluetti-ac50p'],
            [
                'sku' => 'BLUETTI-AC50P',
                'name' => 'BLUETTI AC50P Portable Power Station (504Wh / 700W)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'AC50P',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Power station portabel BLUETTI AC50P, 504Wh LiFePO4, output 700W Pure Sine Wave. Ringan 6,9 kg. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 9219000,        // Harga coret
                'sale_price' => 8299000,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 6900,
                'length_cm' => 28,
                'width_cm' => 20,
                'height_cm' => 22,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => true,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI AC50P 504Wh 700W — Power Station Portabel LiFePO4',
                'meta_description' => 'Jual BLUETTI AC50P power station portabel 504Wh / 700W LiFePO4. Rp 8.299.000 (dari Rp 9.219.000). Ringan 6,9 kg, garansi resmi 5 tahun.',
            ],
        );

        $this->setStock($product, 0);

        $this->command?->info('Produk BLUETTI AC50P berhasil ditambahkan/diperbarui (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 9.219.000 → Rp 8.299.000. Stok 0 — atur lewat menu Stok. Upload gambar lewat Admin → Produk → Edit.');
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
