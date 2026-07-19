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
 * Seeds the Sankelux SPV 1610-540 (Mono) 540 Wp solar panel — produksi dalam
 * negeri, bersertifikat BPPT & SNI 04-3850.2-1995. Spesifikasi dari datasheet
 * resmi Sankelux. Harga coret Rp 2.750.000 → jual Rp 2.150.000, stok 10.
 * Idempotent (firstOrCreate).
 */
class PanelSankelux540Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first();
        $brand = Brand::firstOrCreate(['slug' => 'sankelux'], ['name' => 'Sankelux', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Solar Panel Mono 540 WP Sankelux (SPV 1610-540)</strong> — komponen utama penyedia energi surya yang mengubah sinar matahari menjadi energi listrik. Panel surya handal <strong>produksi dalam negeri</strong>, telah mendapat <strong>sertifikat BPPT</strong> dan memenuhi <strong>SNI 04-3850.2-1995</strong>.</p>
<h4>Keunggulan</h4>
<ul>
<li>⚡ <strong>Daya 540 Wp</strong> — output besar untuk sistem PLTS yang efisien.</li>
<li>🇮🇩 <strong>Produksi dalam negeri</strong>, bersertifikat <strong>BPPT</strong> &amp; <strong>SNI 04-3850.2-1995</strong>.</li>
<li>🔬 <strong>Monocrystalline</strong>, efisiensi modul <strong>20,9%</strong>, sistem hingga <strong>1500 VDC</strong>.</li>
<li>🛡️ <strong>Garansi fungsi 25 tahun</strong> &amp; <strong>garansi fisik 10 tahun</strong>.</li>
</ul>
<h4>Penggunaan</h4>
<ul>
<li>Pembangkit Listrik Tenaga Surya (PLTS)</li>
<li>Penerangan Jalan Umum (PJU) Tenaga Surya</li>
<li>Solar Home System</li>
<li>Lampu Lalu Lintas Tenaga Surya</li>
<li>Pompa Air Tenaga Surya</li>
</ul>
<p><em>*Sebelum order, sebaiknya chat/tanya ketersediaan stok terlebih dahulu.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Merek</th><td>Sankelux</td></tr>
<tr><th>Model</th><td>SPV 1610-540 (Mono)</td></tr>
<tr><th>Daya Maksimum (Pmax)</th><td>540 Wp</td></tr>
<tr><th>Tipe Sel</th><td>Monocrystalline</td></tr>
<tr><th>Efisiensi Modul</th><td>20,9%</td></tr>
<tr><th>Tegangan pada Pmax (Vpmax)</th><td>41,6 V</td></tr>
<tr><th>Arus pada Pmax (Ipmax)</th><td>12,97 A</td></tr>
<tr><th>Tegangan Rangkaian Terbuka (Voc)</th><td>49,6 V</td></tr>
<tr><th>Arus Hubung Singkat (Isc)</th><td>13,86 A</td></tr>
<tr><th>Toleransi Daya</th><td>+3%</td></tr>
<tr><th>Tegangan Sistem Maksimum</th><td>1500 VDC</td></tr>
<tr><th>Active Area</th><td>2.583.252 mm²</td></tr>
<tr><th>Dimensi (P × L × T)</th><td>2278 × 1134 × 40 mm</td></tr>
<tr><th>Berat</th><td>27 kg</td></tr>
<tr><th>Sertifikasi</th><td>BPPT &amp; SNI 04-3850.2-1995 (produksi dalam negeri)</td></tr>
<tr><th>Garansi</th><td>Fungsi 25 tahun, Fisik 10 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'solar-panel-mono-540wp-sankelux'],
            [
                'sku' => 'SANKELUX-SPV1610-540',
                'name' => 'Solar Panel Mono 540 WP Sankelux',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'SPV 1610-540',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Panel surya monocrystalline 540 Wp Sankelux (SPV 1610-540). Produksi dalam negeri, sertifikat BPPT & SNI, efisiensi 20,9%, sistem 1500V. Garansi fungsi 25 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 2750000,        // Harga coret
                'sale_price' => 2150000,   // Harga jual
                'unit' => 'pcs',
                'weight_grams' => 27000,
                'length_cm' => 227.8,
                'width_cm' => 113.4,
                'height_cm' => 4.0,
                'warranty' => 'Garansi Fungsi 25 Tahun, Fisik 10 Tahun',
                'is_new' => true,
                'is_promo' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Solar Panel Mono 540 WP Sankelux SPV 1610-540 — Bersertifikat BPPT & SNI',
                'meta_description' => 'Jual Solar Panel Mono 540 WP Sankelux (SPV 1610-540), monocrystalline, efisiensi 20,9%, sertifikat BPPT & SNI. Rp 2.150.000 (dari Rp 2.750.000). Garansi 25 tahun.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 10);
        }

        $this->command?->info('Produk Sankelux SPV 1610-540 '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 2.750.000 → Rp 2.150.000 • Stok awal 10 pcs. Upload gambar lewat Admin → Produk → Edit.');
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
