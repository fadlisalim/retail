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
 * Seeds the BLUETTI B300K expansion battery (2764.8Wh / 54Ah LiFePO4).
 * This is an EXPANSION BATTERY (bukan power station mandiri) — memperbesar
 * kapasitas host station Bluetti (AC200L / AC200MAX / AC300 / AC500 / Apex 300).
 * Struck price Rp 24.439.000 → sale Rp 21.999.000, stok 1. Specs sourced from the
 * official BLUETTI datasheet (bluettipower.com / user manual) + distributor
 * marketing sheets. Idempotent (firstOrCreate). Images uploaded via admin.
 */
class BluettiB300kSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>BLUETTI B300K — Baterai Ekspansi 2764,8Wh LiFePO4</strong><br>Perbesar kapasitas power station Bluetti Anda tanpa batas. B300K adalah <strong>baterai ekspansi (bukan power station mandiri)</strong> yang dirancang untuk disambungkan ke host station Bluetti agar total kapasitas energi melonjak drastis. Satu modul menambah <strong>2.764,8Wh</strong> tenaga LiFePO4 kelas otomotif — ideal untuk backup listrik rumah lebih lama, kerja off-grid, hingga time-of-use shifting.</p>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>Kapasitas 2.764,8Wh (54Ah)</strong> — tambahan energi besar untuk memperpanjang waktu pakai host station.</li>
<li>🧬 <strong>Sel LiFePO4 kelas otomotif</strong> — 4.000+ siklus pengisian (ke 80%), lebih aman &amp; tahan lama.</li>
<li>🧠 <strong>AI-BMS</strong> — manajemen baterai cerdas dengan casing aluminium kokoh &amp; kipas pendingin efisien.</li>
<li>⚡ <strong>Isi cepat</strong> — saat dipasangkan dengan AC300/AC500, terisi hingga 80% dalam ±45 menit (via host station).</li>
<li>🔗 <strong>Modular &amp; scalable</strong> — susun beberapa modul untuk memperbesar total kapasitas sistem.</li>
<li>🔌 <strong>Port USB-A 12W bawaan</strong> — praktis untuk mengisi perangkat kecil langsung dari baterai.</li>
<li>🛡️ Teknologi LiFePO4 lebih aman, stabil, &amp; tahan panas.</li>
</ul>
<h4>Kompatibilitas</h4>
<p>B300K mendukung host power station Bluetti berikut:</p>
<ul>
<li>✅ BLUETTI AC200L</li>
<li>✅ BLUETTI AC200MAX</li>
<li>✅ BLUETTI AC300</li>
<li>✅ BLUETTI AC500</li>
<li>✅ BLUETTI Apex 300</li>
</ul>
<p><small>*Untuk penyambungan dengan AC500 diperlukan kabel P090D ke P150D (dijual terpisah). B300K hanya dapat diisi daya melalui host station, bukan perangkat mandiri.</small></p>
<p><em>Garansi Resmi 5 Tahun.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>BLUETTI B300K</td></tr>
<tr><th>Tipe</th><td>Baterai Ekspansi (Expansion Battery)</td></tr>
<tr><th>Kapasitas</th><td>2.764,8 Wh (51,2V / 54Ah)</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4 kelas otomotif (AI-BMS)</td></tr>
<tr><th>Siklus</th><td>4.000+ siklus (ke 80% kapasitas)</td></tr>
<tr><th>Kompatibilitas Host</th><td>AC200L, AC200MAX, AC300, AC500, Apex 300</td></tr>
<tr><th>Port USB-A</th><td>12W (5V/2.4A)</td></tr>
<tr><th>Pengisian</th><td>Melalui host station (hingga 80% ±45 menit dgn AC300/AC500)</td></tr>
<tr><th>Berat</th><td>29,5 kg</td></tr>
<tr><th>Dimensi</th><td>525 × 327 × 209 mm</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'bluetti-b300k'],
            [
                'sku' => 'BLUETTI-B300K',
                'name' => 'BLUETTI B300K Baterai Ekspansi (2764.8Wh LiFePO4)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'B300K',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Baterai ekspansi BLUETTI B300K, 2.764,8Wh LiFePO4 (54Ah). Memperbesar kapasitas host station AC200L/AC200MAX/AC300/AC500/Apex 300. Garansi resmi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 24439000,        // Harga coret
                'sale_price' => 21999000,   // Harga jual
                'unit' => 'unit',
                'weight_grams' => 29500,
                'length_cm' => 52.5,
                'width_cm' => 32.7,
                'height_cm' => 20.9,
                'warranty' => 'Garansi Resmi 5 Tahun',
                'is_featured' => false,
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'BLUETTI B300K 2764.8Wh — Baterai Ekspansi LiFePO4 untuk Power Station',
                'meta_description' => 'Jual BLUETTI B300K baterai ekspansi 2.764,8Wh LiFePO4 untuk AC200L/AC200MAX/AC300/AC500/Apex 300. Rp 21.999.000 (dari Rp 24.439.000). Garansi resmi 5 tahun.',
            ],
        );

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 1);
        }

        $this->command?->info('Produk BLUETTI B300K '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 24.439.000 → Rp 21.999.000 • Stok awal 1 • Garansi 5 tahun. Upload gambar lewat Admin → Produk → Edit.');
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
