<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Baterai Lithium Power Wall BEZVOLT 5.12kWh" product. Idempotent
 * (keyed by slug). Upload the datasheet PDF & product images manually via admin.
 */
class BezvoltPowerWallSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::firstOrCreate(['slug' => 'bezvolt'], ['name' => 'BEZVOLT', 'is_active' => true]);
        $category = Category::where('slug', 'baterai')->first();

        $description = <<<'HTML'
<p><strong>Baterai Lithium Power Wall BEZVOLT 5.12kWh</strong><br>Penyimpanan energi <em>wall-mounted</em> 51.2V 100Ah dengan sel LiFePO₄ Grade A.</p>
<p>Simpan energi surya di siang hari dan gunakan kapan saja. Power Wall BEZVOLT menjaga rumah dan usaha Anda tetap menyala saat PLN padam, sekaligus memangkas tagihan listrik. Desainnya yang ringkas dan elegan mudah dipasang di dinding tanpa memakan banyak ruang, serta bisa diparalel saat kebutuhan energi bertambah.</p>
<h4>Keunggulan</h4>
<ul>
<li>Sel Lithium LiFePO₄ Grade A — aman &amp; tahan lama</li>
<li>Kapasitas besar 5.12 kWh (51.2V 100Ah)</li>
<li>Umur pakai panjang hingga 6000+ siklus</li>
<li>BMS cerdas: proteksi over-charge, over-discharge, hubung singkat &amp; suhu</li>
<li>Desain Wall-Mounted ringkas &amp; elegan</li>
<li>Dapat diparalel untuk menambah kapasitas</li>
<li>Kompatibel dengan mayoritas inverter hybrid 48V / 51.2V</li>
<li>Garansi resmi 6 tahun</li>
<li>Cocok untuk rumah, villa, ruko, kantor, klinik, hingga UMKM</li>
</ul>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Merk</th><td>BEZVOLT</td></tr>
<tr><th>Tipe / Spec</th><td>Wall-Mounted Storage Battery 51.2V 100Ah, 5.12kWh</td></tr>
<tr><th>Jenis</th><td>Lithium LiFePO₄</td></tr>
<tr><th>Kapasitas</th><td>5.12 kWh (100 Ah)</td></tr>
<tr><th>Tegangan</th><td>51.2 V</td></tr>
<tr><th>Siklus</th><td>6000+ siklus</td></tr>
<tr><th>Satuan</th><td>pcs</td></tr>
<tr><th>Garansi</th><td>6 Tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::updateOrCreate(
            ['slug' => 'baterai-lithium-power-wall-bezvolt-5120wh'],
            [
                'sku' => 'BEZVOLT-POWERWALL-5120',
                'name' => 'Baterai Lithium Power Wall BEZVOLT 5120 Wh (5.12 kWh)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'Power Wall 5120Wh',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Baterai penyimpanan energi wall-mounted LiFePO₄ 5.12kWh (51.2V 100Ah). Aman, 6000+ siklus, dapat diparalel. Garansi resmi 6 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 14700000,
                'sale_price' => null,
                'unit' => 'pcs',
                'is_taxable' => true,
                'price_includes_tax' => true,
                'stock' => 23,
                'weight_grams' => 50000,
                'requires_freight' => true,
                'warranty' => 'Garansi resmi 6 tahun',
                'is_purchasable' => true,
                'is_featured' => true,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Baterai Lithium Power Wall BEZVOLT 5.12kWh — 51.2V 100Ah LiFePO₄',
                'meta_description' => 'Baterai penyimpanan energi wall-mounted BEZVOLT 5.12kWh (51.2V 100Ah) LiFePO₄. Aman, tahan lama, dapat diparalel. Garansi resmi 6 tahun.',
            ],
        );

        $this->command?->info('Produk Baterai Power Wall BEZVOLT 5.12kWh berhasil ditambahkan/diperbarui (slug: '.$product->slug.').');
        $this->command?->warn('Ingat: upload gambar produk + PDF datasheet lewat Admin → Produk → Edit.');
    }
}
