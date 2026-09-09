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
 * Seeds SATU paket instalasi PLTS BLUETTI Apex 300 (product_type = variable)
 * dengan varian jumlah baterai ekspansi B300K:
 *   Tanpa B300K          — Rp 38.900.000  (2,76 kWh)
 *   + 1× B300K           — Rp 58.900.000  (5,53 kWh)
 *   + 2× B300K           — Rp 78.900.000  (8,29 kWh)
 *   + 3× B300K           — Rp 98.900.000  (11,06 kWh)
 * Tiap B300K +Rp 20.000.000 (termasuk kabel & aksesori). Idempotent.
 * Stok tiap varian 5 (placeholder — sesuaikan di Admin).
 */
class PaketApex300Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-solar-generator')->first();
        $paketCategory = Category::where('slug', 'paket-plts-off-grid')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Paket PLTS BLUETTI Apex 300 + Solar 1.200 Wp</strong> — solusi listrik tenaga surya siap pakai untuk backup rumah &amp; off-grid. Pilih kapasitas baterai sesuai kebutuhan lewat <strong>varian jumlah baterai B300K</strong> di bawah.</p>
<h4>📦 Isi Paket (semua varian)</h4>
<ul>
<li>1× <strong>BLUETTI Apex 300</strong> — power station 2.764,8 Wh / 3.840 W (LiFePO4)</li>
<li>2× <strong>Solar Panel 600 Wp</strong> (total <strong>1.200 Wp</strong>)</li>
<li><strong>Kabel PV 30 meter</strong> + <strong>Bracket</strong> panel surya</li>
<li>Tambahan <strong>baterai BLUETTI B300K</strong> sesuai varian (termasuk kabel &amp; aksesori)</li>
</ul>
<h4>🔋 Pilihan Kapasitas Baterai (Varian)</h4>
<table><tbody>
<tr><th>Konfigurasi</th><th>Energi Baterai (Usable)</th><th>Harga</th></tr>
<tr><td>Tanpa B300K</td><td>2.764,8 Wh (±2,76 kWh)</td><td>Rp 38.900.000</td></tr>
<tr><td>+ 1× B300K</td><td>5.529,6 Wh (±5,53 kWh)</td><td>Rp 58.900.000</td></tr>
<tr><td>+ 2× B300K</td><td>8.294,4 Wh (±8,29 kWh)</td><td>Rp 78.900.000</td></tr>
<tr><td>+ 3× B300K</td><td>11.059,2 Wh (±11,06 kWh)</td><td>Rp 98.900.000</td></tr>
</tbody></table>
<p><em>Setiap penambahan 1 B300K = +Rp 20.000.000 (sudah termasuk kabel &amp; aksesori). Apex 300 mendukung hingga 6× B300K (±19,3 kWh) — hubungi kami untuk konfigurasi lebih besar.</em></p>
<h4>⚡ Kapasitas &amp; Energi</h4>
<ul>
<li><strong>Daya beban yang bisa di-backup:</strong> hingga <strong>3.840 W</strong> (lonjakan 7.680 W) — kuat menyalakan kulkas, TV, lampu, kipas, pompa air, rice cooker, dll secara bersamaan.</li>
<li><strong>Energi yang bisa dipakai dari baterai:</strong> <strong>2,76 – 11,06 kWh</strong> tergantung varian (tiap B300K menambah ±2,76 kWh).</li>
<li><strong>Produksi energi dari panel surya 1.200 Wp:</strong> ±<strong>4,5–5 kWh/hari</strong> (tergantung cuaca &amp; sinar matahari; asumsi 4–5 jam matahari efektif) — mengisi ulang baterai tiap siang.</li>
<li><strong>Gambaran pakai:</strong> energi baterai + isi ulang surya harian cukup untuk kulkas, penerangan LED, TV, kipas, hingga pengisian gadget — ideal untuk backup listrik rumah &amp; area off-grid.</li>
</ul>
<p><em>*Estimasi produksi surya bergantung lokasi &amp; cuaca. Angka baterai adalah kapasitas modul (usable).</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Isi Paket</th><td>Apex 300 + 2× Solar 600Wp (1.200Wp) + Kabel PV 30m + Bracket (+ B300K sesuai varian)</td></tr>
<tr><th>Unit Utama</th><td>BLUETTI Apex 300 (2.764,8 Wh / 3.840 W, LiFePO4)</td></tr>
<tr><th>Daya Output (Backup)</th><td>3.840 W (lonjakan 7.680 W)</td></tr>
<tr><th>Energi Baterai (Usable)</th><td>2,76 kWh (Tanpa B300K) s/d 11,06 kWh (+3 B300K)</td></tr>
<tr><th>Baterai Ekspansi</th><td>B300K 2.764,8 Wh/unit — hingga 6 unit (±19,3 kWh)</td></tr>
<tr><th>Total Panel Surya</th><td>1.200 Wp (2 × 600 Wp)</td></tr>
<tr><th>Estimasi Produksi Surya</th><td>±4,5–5 kWh/hari (tergantung cuaca)</td></tr>
<tr><th>Input Surya Apex 300</th><td>hingga 1.200 W (12–150V, MC4)</td></tr>
<tr><th>Kabel PV</th><td>30 meter</td></tr>
<tr><th>Aksesori</th><td>Bracket / dudukan panel surya + kabel baterai</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'paket-plts-bluetti-apex-300-solar-1200wp'],
            [
                'sku' => 'PAKET-APEX300-SOLAR1200',
                'name' => 'Paket PLTS BLUETTI Apex 300 + Solar 1.200 Wp (2×600) + Kabel & Bracket',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Paket PLTS siap pakai: BLUETTI Apex 300 (3.840W) + solar 1.200Wp (2×600) + kabel PV 30m + bracket. Pilih kapasitas baterai 2,76–11,06 kWh via varian B300K.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 38900000,   // harga "mulai dari" (varian Tanpa B300K)
                'unit' => 'paket',
                'weight_grams' => 85000,
                'requires_freight' => true,
                'is_new' => true,
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Paket PLTS BLUETTI Apex 300 + Solar 1.200 Wp — Backup Listrik Rumah',
                'meta_description' => 'Paket PLTS BLUETTI Apex 300 + solar 1.200Wp + kabel & bracket. Kapasitas baterai 2,76–11,06 kWh (varian B300K). Backup hingga 3.840W. Mulai Rp 38.900.000.',
            ],
        );

        // Jadikan variable & sync kategori hanya saat baru dibuat.
        if ($product->wasRecentlyCreated) {
            $ids = array_values(array_filter([$product->category_id, $paketCategory?->id]));
            $product->categories()->sync($ids);
        }

        // Paket 85–174 kg wajib jalur kargo (ongkir dikonfirmasi) — dilengkapi
        // juga untuk baris yang sudah ada di produksi.
        if (! $product->requires_freight) {
            $product->forceFill(['requires_freight' => true])->save();
        }

        // Varian: jumlah baterai B300K (0..3). +20jt per B300K.
        $variants = [
            ['b' => 0, 'name' => 'Tanpa B300K',  'price' => 38900000, 'kwh' => '2,76 kWh'],
            ['b' => 1, 'name' => '+ 1× B300K',   'price' => 58900000, 'kwh' => '5,53 kWh'],
            ['b' => 2, 'name' => '+ 2× B300K',   'price' => 78900000, 'kwh' => '8,29 kWh'],
            ['b' => 3, 'name' => '+ 3× B300K',   'price' => 98900000, 'kwh' => '11,06 kWh'],
        ];

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => 'PAKET-APEX300-B'.$v['b']],
                [
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'option_values' => ['Baterai Ekspansi' => $v['name'].' ('.$v['kwh'].')'],
                    'price' => $v['price'],
                    'sale_price' => null,
                    'weight_grams' => 85000 + ($v['b'] * 29500), // + berat B300K ±29,5 kg/unit
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
            $this->setVariantStock($product, $variant, 5); // placeholder — sesuaikan di Admin
        }

        $this->command?->info('Paket Apex 300 (4 varian B300K) '.($product->wasRecentlyCreated ? 'ditambahkan' : 'diperbarui').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga varian: Rp 38,9jt / 58,9jt / 78,9jt / 98,9jt • Stok tiap varian 5 (placeholder). Upload gambar lewat Admin.');
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
