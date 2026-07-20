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
 * Seeds the Mitsubishi Electric PV-MJE275FB 275 Wp monocrystalline module —
 * barang BARU sisa proyek. Spesifikasi dari datasheet resmi Mitsubishi (MJE
 * Series, Made in Japan). Harga coret Rp 2.250.000 → jual Rp 1.350.000,
 * garansi 5 tahun. Idempotent (firstOrCreate).
 *
 * CATATAN: stok awal di-set 10 pcs sebagai placeholder (tidak disebutkan) —
 * sesuaikan lewat Admin bila berbeda.
 */
class PanelMitsubishiMje275Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first();
        $surplusCategory = Category::where('slug', 'barang-sisa-proyek-baru')->first();
        $brand = Brand::firstOrCreate(['slug' => 'mitsubishi-electric'], ['name' => 'Mitsubishi Electric', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Panel Surya Mitsubishi Electric MJE 275 Wp (PV-MJE275FB)</strong> — modul surya monocrystalline premium <strong>buatan Jepang (Made in Japan)</strong>. Unit ini adalah <strong>barang baru sisa proyek</strong>: kondisi baru, kualitas premium, harga jauh lebih hemat.</p>
<h4>Keunggulan</h4>
<ul>
<li>🇯🇵 <strong>Mitsubishi Electric — Made in Japan</strong>, kualitas &amp; reliabilitas kelas premium.</li>
<li>🔬 <strong>Sel monocrystalline 4 busbar</strong> + kaca anti-reflektif → output lebih optimal.</li>
<li>🌊 <strong>Tahan area dekat laut (saltwater)</strong> — frame lapis ganda anti-korosi.</li>
<li>💪 <strong>Lolos uji beban statis IEC 5400 Pa</strong>, junction box 4 lapis, solder bebas timbal.</li>
<li>➕ <strong>Toleransi positif (+5/-0%)</strong> — output tidak pernah di bawah nominal.</li>
<li>⚡ Sistem hingga <strong>1000V</strong>, efisiensi modul <strong>16,7%</strong>.</li>
</ul>
<p><em>Cocok untuk PLTS rumah, kantor, hingga proyek. Barang baru sisa proyek — stok terbatas.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Merek</th><td>Mitsubishi Electric</td></tr>
<tr><th>Model</th><td>PV-MJE275FB (MJE Series)</td></tr>
<tr><th>Kondisi</th><td>Baru — Sisa Proyek</td></tr>
<tr><th>Daya Maksimum (Pmax)</th><td>275 Wp</td></tr>
<tr><th>Toleransi Daya</th><td>+5 / -0%</td></tr>
<tr><th>Tipe Sel</th><td>Monocrystalline silicon 156,75 × 156,75 mm — 60 sel (4 busbar)</td></tr>
<tr><th>Efisiensi Modul</th><td>16,7%</td></tr>
<tr><th>Tegangan pada Pmax (Vmp)</th><td>31,3 V</td></tr>
<tr><th>Arus pada Pmax (Imp)</th><td>8,79 A</td></tr>
<tr><th>Tegangan Rangkaian Terbuka (Voc)</th><td>38,3 V</td></tr>
<tr><th>Arus Hubung Singkat (Isc)</th><td>9,36 A</td></tr>
<tr><th>NOCT</th><td>46,0°C</td></tr>
<tr><th>Tegangan Sistem Maksimum</th><td>1000 V</td></tr>
<tr><th>Fuse Rating</th><td>15 A</td></tr>
<tr><th>Konektor</th><td>SMK (PV-03), kabel 1175 mm</td></tr>
<tr><th>Dimensi</th><td>1657 × 994 × 46 mm</td></tr>
<tr><th>Berat</th><td>19 kg</td></tr>
<tr><th>Sertifikat</th><td>IEC 61215, IEC 61730, UL1703 · Made in Japan</td></tr>
<tr><th>Garansi</th><td>5 Tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'panel-surya-mitsubishi-mje275fb-275wp'],
            [
                'sku' => 'MITSUBISHI-MJE275FB',
                'name' => 'Panel Surya Mitsubishi 275 Wp Mono (MJE275FB) — Baru Sisa Proyek',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'PV-MJE275FB',
                'product_type' => 'simple',
                'condition' => 'new_project_surplus',
                'short_description' => 'Panel surya Mitsubishi Electric 275 Wp monocrystalline (PV-MJE275FB), Made in Japan. Barang baru sisa proyek. Efisiensi 16,7%, 1000V, tahan dekat laut. Garansi 5 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 2250000,        // Harga coret
                'sale_price' => 1350000,   // Harga jual
                'unit' => 'pcs',
                'weight_grams' => 19000,
                'length_cm' => 165.7,
                'width_cm' => 99.4,
                'height_cm' => 4.6,
                'warranty' => 'Garansi 5 Tahun',
                'is_promo' => true,
                'is_clearance' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Panel Surya Mitsubishi 275 Wp Mono MJE275FB — Baru Sisa Proyek (Made in Japan)',
                'meta_description' => 'Jual Panel Surya Mitsubishi Electric 275 Wp monocrystalline (PV-MJE275FB), Made in Japan, barang baru sisa proyek. Rp 1.350.000 (dari Rp 2.250.000). Garansi 5 tahun.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            // Muncul juga di kategori "Barang Sisa Proyek → Baru".
            $ids = array_values(array_filter([$product->category_id, $surplusCategory?->id]));
            $product->categories()->sync($ids);
            $this->setStock($product, 3);
        }

        $this->command?->info('Produk Mitsubishi MJE275FB '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 2.250.000 → Rp 1.350.000 • Stok awal 3 pcs • Garansi 5 tahun. Upload gambar lewat Admin.');
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
