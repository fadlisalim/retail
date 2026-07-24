<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Paket PLTS Hybrid: BEZVOLT POWERHOME 6-05 (inverter hybrid 6kW + baterai
 * LiFePO4 5,12 kWh) + solar panel, 5 varian kombinasi. Harga = bundling
 * Rp 29.500.000 + panel Rp 6jt/kWp (include bracket) + baterai tambahan
 * Rp 16jt/5kWh:
 *   2 kWp / 5 kWh   → 41.500.000
 *   2 kWp / 10 kWh  → 57.500.000
 *   3 kWp / 10 kWh  → 63.500.000
 *   4 kWp / 10 kWh  → 69.500.000
 *   5 kWp / 15 kWh  → 91.500.000
 * Idempotent. Stok awal per varian 2 = placeholder (sesuaikan di Admin).
 */
class PaketPowerhomeSolarSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'paket-plts-hybrid')->first();
        $rumah = Category::where('slug', 'paket-plts-rumah')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bezvolt'], ['name' => 'Bezvolt', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Paket PLTS Hybrid siap pasang</strong> berbasis <strong>BEZVOLT POWERHOME 6-05</strong>: inverter hybrid <strong>6.000W dual MPPT</strong> + baterai lithium <strong>LiFePO4 Grade A 5,12 kWh (51,2V 100Ah)</strong> per unit — dipadukan dengan solar panel sesuai kebutuhan Kakak. Hemat tagihan PLN hingga 70%, listrik tetap menyala otomatis saat PLN padam.</p>

<h4>Pilih Kombinasi (Varian)</h4>
<ul>
<li>🏠 <strong>2 kWp · Baterai 5 kWh</strong> — rumah kecil–menengah, produksi ±7–8 kWh/hari.</li>
<li>🏠 <strong>2 kWp · Baterai 10 kWh</strong> — backup lebih lama untuk malam hari.</li>
<li>🏡 <strong>3 kWp · Baterai 10 kWh</strong> — rumah menengah, produksi ±10,5–12 kWh/hari.</li>
<li>🏡 <strong>4 kWp · Baterai 10 kWh</strong> — rumah menengah–besar, produksi ±14–16 kWh/hari.</li>
<li>🏘️ <strong>5 kWp · Baterai 15 kWh</strong> — rumah besar/kebutuhan tinggi, produksi ±17,5–20 kWh/hari.</li>
</ul>

<h4>Isi Paket</h4>
<ul>
<li>⚡ <strong>1× Inverter Hybrid BEZVOLT 6.000W</strong> — dual MPPT (2 tracker), Pure Sine Wave, parallel support hingga 6 unit, monitoring real-time via aplikasi.</li>
<li>🔋 <strong>Baterai Lithium BEZVOLT 5,12 kWh (51,2V 100Ah)</strong> — jumlah unit sesuai varian (5 kWh = 1 unit, 10 kWh = 2 unit, 15 kWh = 3 unit). LiFePO4 Grade A, aman &amp; tahan lama.</li>
<li>☀️ <strong>Solar panel total sesuai varian</strong> (2–5 kWp) — merek/jumlah lembar high-output menyesuaikan stok, dikonfirmasi admin.</li>
<li>🔩 <strong>Bracket/mounting solar panel</strong> sudah termasuk.</li>
</ul>

<h4>Kenapa Paket Ini?</h4>
<ul>
<li>✅ Komponen dijamin <strong>kompatibel</strong> satu ekosistem BEZVOLT.</li>
<li>🔌 <strong>Backup otomatis</strong> — listrik tetap menyala saat PLN padam, tanpa jeda berarti.</li>
<li>📈 <strong>Expandable</strong> — baterai &amp; inverter bisa ditambah di kemudian hari (parallel hingga 6 unit).</li>
<li>🛡️ <strong>Garansi resmi 5 tahun</strong> unit BEZVOLT, after-sales by Rekasurya.</li>
</ul>

<p><em>Harga <strong>belum termasuk instalasi</strong> serta aksesori spesifik lokasi (kabel/proteksi menyesuaikan kondisi rumah — dikonfirmasi saat survei). Tim Rekasurya siap survei &amp; pasang: minta penawaran instalasi ke admin. Sebelum order, chat admin dulu untuk konfirmasi stok.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Inverter</th><td>BEZVOLT Hybrid 6.000W (6 kW) · single phase · dual MPPT (2 tracker) · Pure Sine Wave · parallel hingga 6 unit · monitoring real-time</td></tr>
<tr><th>Baterai (per unit)</th><td>BEZVOLT Lithium LiFePO4 Grade A · 5,12 kWh · 51,2V 100Ah</td></tr>
<tr><th>Kombinasi Baterai</th><td>5 kWh = 1 unit · 10 kWh = 2 unit · 15 kWh = 3 unit (kapasitas aktual 5,12 / 10,24 / 15,36 kWh)</td></tr>
<tr><th>Array Panel Surya</th><td>Total 2–5 kWp sesuai varian (merek/jumlah lembar dikonfirmasi admin) · bracket termasuk</td></tr>
<tr><th>Estimasi Produksi</th><td>±3,5–4 kWh per kWp per hari (tergantung lokasi &amp; cuaca)</td></tr>
<tr><th>Tipe Sistem</th><td>Hybrid (surya + PLN + baterai, backup otomatis saat padam)</td></tr>
<tr><th>Instalasi</th><td>Belum termasuk (tersedia oleh tim Rekasurya — minta penawaran)</td></tr>
<tr><th>Garansi</th><td>Resmi 5 tahun (unit BEZVOLT)</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'paket-plts-hybrid-bezvolt-powerhome-6-05-solar-panel'],
            [
                'sku' => 'PAKET-PH605-SOLAR',
                'name' => 'Paket PLTS Hybrid BEZVOLT POWERHOME 6-05 + Solar Panel',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'POWERHOME 6-05',
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Paket PLTS hybrid BEZVOLT POWERHOME 6-05: inverter hybrid 6kW dual MPPT + baterai LiFePO4 + solar panel (2–5 kWp, bracket termasuk). 5 pilihan kombinasi, garansi resmi 5 tahun. Harga exc instalasi.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 41500000, // "mulai dari" — varian 2 kWp / 5 kWh
                'unit' => 'paket',
                'weight_grams' => 230000,
                'warranty' => 'Garansi Resmi 5 Tahun (unit BEZVOLT)',
                'keywords' => 'paket plts hybrid, bezvolt, powerhome, inverter hybrid 6kw, baterai lithium, 2 kwp, 3 kwp, 4 kwp, 5 kwp, plts rumah, anti mati lampu',
                'is_new' => true,
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Paket PLTS Hybrid BEZVOLT POWERHOME 6-05 + Solar Panel 2–5 kWp — Mulai Rp 41,5 Juta',
                'meta_description' => 'Paket PLTS hybrid BEZVOLT: inverter 6kW dual MPPT + baterai LiFePO4 5-15 kWh + panel surya 2-5 kWp (bracket termasuk). Mulai Rp 41.500.000, garansi resmi 5 tahun.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            $product->categories()->sync(array_values(array_filter([$category?->id, $rumah?->id])));
        }

        // Harga: 29,5jt bundling + 6jt/kWp + 16jt per baterai tambahan (5kWh).
        // Berat (estimasi kargo): inverter+1 baterai ±80kg, +50kg/baterai, +75kg/kWp.
        $variants = [
            ['sku' => 'PH605-2KWP-5KWH', 'name' => '2 kWp · Baterai 5 kWh', 'price' => 41500000, 'weight' => 230000],
            ['sku' => 'PH605-2KWP-10KWH', 'name' => '2 kWp · Baterai 10 kWh', 'price' => 57500000, 'weight' => 280000],
            ['sku' => 'PH605-3KWP-10KWH', 'name' => '3 kWp · Baterai 10 kWh', 'price' => 63500000, 'weight' => 355000],
            ['sku' => 'PH605-4KWP-10KWH', 'name' => '4 kWp · Baterai 10 kWh', 'price' => 69500000, 'weight' => 430000],
            ['sku' => 'PH605-5KWP-15KWH', 'name' => '5 kWp · Baterai 15 kWh', 'price' => 91500000, 'weight' => 555000],
        ];

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => $v['sku']],
                [
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'option_values' => ['Kombinasi' => $v['name']],
                    'price' => $v['price'],
                    'sale_price' => null,
                    'weight_grams' => $v['weight'],
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );

            if ($variant->wasRecentlyCreated) {
                $this->setVariantStock($product, $variant, 2); // placeholder — sesuaikan di Admin
            }
        }

        $this->command?->info('Paket POWERHOME 6-05 + Solar '.($product->wasRecentlyCreated ? 'ditambahkan' : 'diperbarui').': 41,5jt / 57,5jt / 63,5jt / 69,5jt / 91,5jt.');
        $this->command?->warn('Stok awal tiap varian 2 (placeholder — sesuaikan di Admin, stok bundling saat ini terbatas). Upload foto lewat Admin → Produk.');
    }

    private function setVariantStock(Product $product, ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_variant_id', $variant->id)->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, $variant, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder)');
        }
    }
}
