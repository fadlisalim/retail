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
 * Paket PLTS Hybrid "Bezvolt Power Home 6000W 1 Fasa" — inverter hybrid 6kW
 * + baterai LiFePO4 5,12 kWh + solar panel, 6 varian kombinasi.
 * Harga = base (inverter + baterai 5 kWh) Rp 32,5jt + panel Rp 6jt/kWp
 * + baterai tambahan Rp 16jt/5kWh:
 *   PV 0 kWp / 5 kWh   → 32.500.000
 *   PV 2 kWp / 5 kWh   → 44.500.000
 *   PV 3 kWp / 5 kWh   → 50.500.000
 *   PV 3 kWp / 10 kWh  → 66.500.000
 *   PV 4 kWp / 10 kWh  → 72.500.000
 *   PV 5 kWp / 15 kWh  → 94.500.000
 * Termasuk mounting panel; DILUAR kabel, aksesoris, panel proteksi, instalasi.
 * Garansi: inverter 5 thn, baterai 6 thn, panel 10 thn.
 * Idempotent (update-style); varian lama di luar lineup dinonaktifkan.
 */
class PaketPowerhomeSolarSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'paket-plts-hybrid')->first();
        $rumah = Category::where('slug', 'paket-plts-rumah')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bezvolt'], ['name' => 'Bezvolt', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Paket PLTS Hybrid siap pasang</strong> berbasis <strong>Bezvolt Power Home 6000W (1 fasa)</strong>: inverter hybrid <strong>6.000W dual MPPT</strong> + baterai lithium <strong>LiFePO4 Grade A 5,12 kWh (51,2V 100Ah)</strong> per unit — pilih kombinasi panel surya &amp; kapasitas baterai sesuai kebutuhan. Hemat tagihan PLN, listrik tetap menyala otomatis saat PLN padam.</p>

<h4>Pilih Kombinasi (Varian)</h4>
<ul>
<li>🔌 <strong>PV 0 kWp · Baterai 5 kWh</strong> — tanpa panel dulu: jadi backup/UPS rumah (charge dari PLN), panel bisa ditambah kapan saja.</li>
<li>🏠 <strong>PV 2 kWp · Baterai 5 kWh</strong> — rumah kecil–menengah, produksi ±7–8 kWh/hari.</li>
<li>🏠 <strong>PV 3 kWp · Baterai 5 kWh</strong> — produksi lebih besar ±10,5–12 kWh/hari, baterai standar.</li>
<li>🏡 <strong>PV 3 kWp · Baterai 10 kWh</strong> — malam lebih panjang: AC bisa menyala semalaman dari baterai.</li>
<li>🏡 <strong>PV 4 kWp · Baterai 10 kWh</strong> — rumah menengah–besar, produksi ±14–16 kWh/hari.</li>
<li>🏘️ <strong>PV 5 kWp · Baterai 15 kWh</strong> — rumah besar/kebutuhan tinggi, produksi ±17,5–20 kWh/hari.</li>
</ul>

<h4>Ilustrasi Beban yang Bisa Disuplai</h4>
<p><em>Semua varian memakai inverter 6.000W — beban serentak hingga ±6.000W. Perbedaan antar varian ada di energi harian (panel) &amp; daya tahan malam (baterai). Angka di bawah ilustrasi, aktual tergantung pemakaian &amp; cuaca.</em></p>
<table><tbody>
<tr><th>Varian</th><th>Contoh skenario harian</th></tr>
<tr><td><strong>PV 0 · 5 kWh</strong></td><td>Saat PLN padam: kulkas + 6 lampu + TV + WiFi + kipas (±300W) bertahan <strong>±12–14 jam</strong>; atau tambah AC ½ PK ±4–5 jam.</td></tr>
<tr><td><strong>PV 2 kWp · 5 kWh</strong></td><td>Siang gratis dari matahari: kulkas, WiFi, lampu, TV, mesin cuci, pompa air. Malam dari baterai: kulkas + lampu + TV + WiFi sampai pagi. Cocok rumah 900–1300 VA.</td></tr>
<tr><td><strong>PV 3 kWp · 5 kWh</strong></td><td>Seperti di atas + siang kuat untuk AC ½–1 PK beberapa jam, setrika/magic com, kerja dari rumah full. Cocok rumah 1300 VA.</td></tr>
<tr><td><strong>PV 3 kWp · 10 kWh</strong></td><td>Malam jauh lebih lega: <strong>AC ½ PK semalaman (±8 jam)</strong> + kulkas + lampu + TV masih tersisa cadangan. Cocok rumah 1300–2200 VA.</td></tr>
<tr><td><strong>PV 4 kWp · 10 kWh</strong></td><td>Siang: 2 AC + seluruh beban dasar rumah. Malam: 1 AC semalaman + kulkas + lampu + WiFi. Cocok rumah 2200 VA.</td></tr>
<tr><td><strong>PV 5 kWp · 15 kWh</strong></td><td>Rumah besar: siang 2–3 AC + pompa + 2 kulkas; malam <strong>2 AC ½ PK semalaman</strong> + beban dasar. Cocok rumah 2200–3500 VA.</td></tr>
</tbody></table>

<h4>Isi Paket</h4>
<ul>
<li>⚡ <strong>1× Inverter Hybrid Bezvolt 6.000W (1 fasa)</strong> — dual MPPT, Pure Sine Wave, parallel support, monitoring real-time.</li>
<li>🔋 <strong>Baterai Lithium Bezvolt 5,12 kWh (51,2V 100Ah)</strong> — 5 kWh = 1 unit, 10 kWh = 2 unit, 15 kWh = 3 unit (kapasitas aktual 5,12 / 10,24 / 15,36 kWh).</li>
<li>☀️ <strong>Solar panel total sesuai varian</strong> (0–5 kWp; merek/jumlah lembar high-output menyesuaikan stok, dikonfirmasi admin).</li>
<li>🔩 <strong>Mounting solar panel termasuk.</strong></li>
</ul>
<p><strong>Di luar paket:</strong> kabel, aksesoris, panel proteksi, dan instalasi (menyesuaikan kondisi rumah — dikonfirmasi saat survei; tim Rekasurya siap survei &amp; pasang, minta penawaran ke admin).</p>

<h4>Garansi</h4>
<ul>
<li>🛡️ Inverter <strong>5 tahun</strong> · Baterai <strong>6 tahun</strong> · Panel surya <strong>10 tahun</strong> — resmi, after-sales by Rekasurya.</li>
</ul>

<p><em>Sebelum order, chat admin dulu untuk konfirmasi stok &amp; jadwal.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Inverter</th><td>Bezvolt Power Home Hybrid 6.000W (6 kW) · 1 fasa · dual MPPT · Pure Sine Wave · parallel support · monitoring real-time</td></tr>
<tr><th>Baterai (per unit)</th><td>Bezvolt Lithium LiFePO4 Grade A · 5,12 kWh · 51,2V 100Ah</td></tr>
<tr><th>Kombinasi Baterai</th><td>5 kWh = 1 unit · 10 kWh = 2 unit · 15 kWh = 3 unit (aktual 5,12 / 10,24 / 15,36 kWh)</td></tr>
<tr><th>Array Panel Surya</th><td>Total 0–5 kWp sesuai varian (merek/jumlah lembar dikonfirmasi admin)</td></tr>
<tr><th>Estimasi Produksi</th><td>±3,5–4 kWh per kWp per hari (tergantung lokasi &amp; cuaca)</td></tr>
<tr><th>Termasuk</th><td>Panel surya, inverter, baterai, mounting solar panel</td></tr>
<tr><th>Tidak Termasuk</th><td>Kabel, aksesoris, panel proteksi, instalasi (dikonfirmasi saat survei)</td></tr>
<tr><th>Tipe Sistem</th><td>Hybrid 1 fasa (surya + PLN + baterai, backup otomatis saat padam)</td></tr>
<tr><th>Garansi</th><td>Inverter 5 tahun · Baterai 6 tahun · Panel surya 10 tahun</td></tr>
</tbody></table>
HTML;

        // Cari berdasarkan SKU dulu: slug produk di produksi bisa sudah diedit
        // admin, dan SKU unik — kalau dicari berdasarkan slug lama, seeder akan
        // mencoba membuat produk baru dan gagal "Duplicate entry" di SKU.
        $existing = Product::where('sku', 'PAKET-PH605-SOLAR')
            ->orWhere('slug', 'paket-plts-hybrid-bezvolt-powerhome-6-05-solar-panel')
            ->first();

        $product = $existing ?? Product::create(
            [
                'slug' => 'paket-plts-hybrid-bezvolt-powerhome-6-05-solar-panel',
                'sku' => 'PAKET-PH605-SOLAR',
                'name' => 'Paket PLTS Hybrid Bezvolt Power Home 6000W 1 Fasa',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'Power Home 6000W',
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Paket PLTS hybrid Bezvolt Power Home 6000W 1 fasa: inverter hybrid 6kW dual MPPT + baterai LiFePO4 5-15 kWh + solar panel 0-5 kWp (mounting termasuk). 6 pilihan kombinasi. Garansi inverter 5 thn, baterai 6 thn, panel 10 thn.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 32500000, // "mulai dari" — varian PV 0 kWp / 5 kWh
                'sale_price' => null,
                'unit' => 'paket',
                'weight_grams' => 80000,
                'requires_freight' => true, // 80–555 kg: jalur kargo, ongkir dikonfirmasi
                'warranty' => 'Inverter 5 Thn · Baterai 6 Thn · Panel Surya 10 Thn',
                'keywords' => 'paket plts hybrid, bezvolt, power home, inverter hybrid 6kw, 1 fasa, baterai lithium, 2 kwp, 3 kwp, 4 kwp, 5 kwp, plts rumah, anti mati lampu',
                'is_new' => true,
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Paket PLTS Hybrid Bezvolt Power Home 6000W 1 Fasa + Solar Panel 0–5 kWp — Mulai Rp 32,5 Juta',
                'meta_description' => 'Paket PLTS hybrid Bezvolt Power Home 6000W 1 fasa: baterai LiFePO4 5-15 kWh + panel 0-5 kWp, mounting termasuk. 6 kombinasi mulai Rp 32.500.000. Garansi inverter 5 thn, baterai 6 thn, panel 10 thn.',
            ],
        );

        if ($existing) {
            // Produk sudah ada — nama/slug/deskripsi/harga editan admin tidak
            // ditimpa; cukup pastikan jalur kargo untuk paket 80–555 kg.
            if (! $product->requires_freight) {
                $product->forceFill(['requires_freight' => true])->save();
            }
        } else {
            $product->categories()->sync(array_values(array_filter([$category?->id, $rumah?->id])));
        }

        // Harga: base 32,5jt (inverter + 1 baterai) + 6jt/kWp + 16jt/baterai tambahan.
        // Berat (estimasi kargo): inverter+1 baterai ±80kg, +50kg/baterai, +75kg/kWp.
        $variants = [
            ['sku' => 'PH605-0KWP-5KWH', 'name' => 'PV 0 kWp · Baterai 5 kWh', 'price' => 32500000, 'weight' => 80000],
            ['sku' => 'PH605-2KWP-5KWH', 'name' => 'PV 2 kWp · Baterai 5 kWh', 'price' => 44500000, 'weight' => 230000],
            ['sku' => 'PH605-3KWP-5KWH', 'name' => 'PV 3 kWp · Baterai 5 kWh', 'price' => 50500000, 'weight' => 305000],
            ['sku' => 'PH605-3KWP-10KWH', 'name' => 'PV 3 kWp · Baterai 10 kWh', 'price' => 66500000, 'weight' => 355000],
            ['sku' => 'PH605-4KWP-10KWH', 'name' => 'PV 4 kWp · Baterai 10 kWh', 'price' => 72500000, 'weight' => 430000],
            ['sku' => 'PH605-5KWP-15KWH', 'name' => 'PV 5 kWp · Baterai 15 kWh', 'price' => 94500000, 'weight' => 555000],
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

        // Varian lama di luar lineup final (mis. 2 kWp · 10 kWh) dinonaktifkan.
        ProductVariant::where('product_id', $product->id)
            ->whereNotIn('sku', array_column($variants, 'sku'))
            ->update(['is_active' => false]);

        $this->command?->info('Paket Bezvolt Power Home 6000W: 32,5 / 44,5 / 50,5 / 66,5 / 72,5 / 94,5 jt (6 varian).');
        $this->command?->warn('Stok varian baru = 2 (placeholder — sesuaikan di Admin). Upload foto lewat Admin → Produk.');
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
