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
 * Seeds the Canadian Solar HiKu CS3W-440MS 440 Wp mono PERC module (barang baru).
 * Spesifikasi dari datasheet resmi Canadian Solar HiKu CS3W-MS. Harga coret
 * Rp 2.500.000 → jual Rp 1.800.000, stok 3. Idempotent (firstOrCreate).
 */
class PanelCanadianHiku440Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first();
        $brand = Brand::firstOrCreate(['slug' => 'canadian-solar'], ['name' => 'Canadian Solar', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Solar Panel Canadian Solar HiKu 440 Wp Mono (CS3W-440MS)</strong> — modul surya <strong>High Power Mono PERC</strong> dengan sel <strong>144 half-cut</strong>. Salah satu merek panel surya paling tepercaya di dunia. Kondisi <strong>baru</strong>.</p>
<h4>Keunggulan</h4>
<ul>
<li>⚡ <strong>Daya 440 Wp</strong>, efisiensi modul <strong>19,9%</strong> — output tinggi, hemat ruang.</li>
<li>🔬 <strong>Teknologi Mono PERC Half-Cut (144 sel)</strong> — rugi daya lebih rendah &amp; toleransi bayangan lebih baik.</li>
<li>🌡️ <strong>Koefisien suhu rendah</strong> (Pmax -0,34%/°C, NMOT 41±3°C) — tetap produktif saat panas.</li>
<li>❄️ <strong>Tahan beban salju/angin hingga 5400 Pa</strong> &amp; minim risiko micro-crack.</li>
<li>🛡️ <strong>Garansi produk 12 tahun</strong> &amp; <strong>garansi performa linear 25 tahun</strong> (degradasi tahun ke-1 ≤2%, selanjutnya ≤0,55%/tahun).</li>
<li>🔌 Tegangan sistem hingga <strong>1500 V</strong> — cocok untuk PLTS rumah, komersial, &amp; proyek.</li>
</ul>
<p><em>Panel utama berkualitas dunia untuk sistem tenaga surya yang efisien &amp; awet.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Merek</th><td>Canadian Solar</td></tr>
<tr><th>Model</th><td>HiKu CS3W-440MS</td></tr>
<tr><th>Daya Maksimum (Pmax)</th><td>440 Wp</td></tr>
<tr><th>Tipe Sel</th><td>Monocrystalline PERC Half-Cut — 144 sel [2 × (12 × 6)]</td></tr>
<tr><th>Efisiensi Modul</th><td>19,9%</td></tr>
<tr><th>Tegangan pada Pmax (Vmp)</th><td>40,1 V</td></tr>
<tr><th>Arus pada Pmax (Imp)</th><td>10,98 A</td></tr>
<tr><th>Tegangan Rangkaian Terbuka (Voc)</th><td>48,3 V</td></tr>
<tr><th>Arus Hubung Singkat (Isc)</th><td>11,53 A</td></tr>
<tr><th>Koefisien Suhu (Pmax)</th><td>-0,34%/°C</td></tr>
<tr><th>NMOT</th><td>41 ± 3°C</td></tr>
<tr><th>Suhu Operasi</th><td>-40°C hingga +85°C</td></tr>
<tr><th>Tegangan Sistem Maksimum</th><td>1500 V</td></tr>
<tr><th>Beban Maksimum</th><td>hingga 5400 Pa</td></tr>
<tr><th>Kaca Depan</th><td>Tempered 3,2 mm</td></tr>
<tr><th>Frame</th><td>Aluminium anodized (crossbar enhanced)</td></tr>
<tr><th>Dimensi</th><td>2108 × 1048 × 40 mm</td></tr>
<tr><th>Berat</th><td>24,9 kg</td></tr>
<tr><th>Sertifikat</th><td>IEC 61215 / IEC 61730 / UL 61730</td></tr>
<tr><th>Garansi</th><td>Produk 12 tahun, Performa 25 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'solar-panel-canadian-solar-hiku-440wp'],
            [
                'sku' => 'CANADIANSOLAR-CS3W-440MS',
                'name' => 'Solar Panel Canadian Solar HiKu 440 Wp Mono (CS3W-440MS)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'CS3W-440MS',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Panel surya Canadian Solar HiKu 440 Wp mono PERC half-cut (CS3W-440MS). Efisiensi 19,9%, sistem 1500V, tahan beban 5400Pa. Garansi produk 12 tahun, performa 25 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 2500000,        // Harga coret
                'sale_price' => 1800000,   // Harga jual
                'unit' => 'pcs',
                'weight_grams' => 24900,
                'length_cm' => 210.8,
                'width_cm' => 104.8,
                'height_cm' => 4.0,
                'warranty' => 'Garansi Produk 12 Tahun, Performa 25 Tahun',
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Solar Panel Canadian Solar HiKu 440 Wp Mono CS3W-440MS — PERC Half-Cut',
                'meta_description' => 'Jual Solar Panel Canadian Solar HiKu 440 Wp mono PERC (CS3W-440MS), efisiensi 19,9%, 1500V. Rp 1.800.000 (dari Rp 2.500.000). Garansi 12/25 tahun.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 3);
        }

        $this->command?->info('Produk Canadian Solar CS3W-440MS '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 2.500.000 → Rp 1.800.000 • Stok awal 3 pcs • Garansi 12/25 tahun. Upload gambar lewat Admin.');
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
