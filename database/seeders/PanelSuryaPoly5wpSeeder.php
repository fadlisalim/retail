<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds the Panel Surya Polycrystalline 5 WP (model GH5P-18) — a small poly panel
 * for lampu taman, charger kecil, proyek DIY/edukasi, dan elektronik kecil.
 * Harga jual Rp 84.000, stok 20. Idempotent (firstOrCreate) — aman di-run ulang.
 */
class PanelSuryaPoly5wpSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-polycrystalline')->first();

        $description = <<<'HTML'
<p><strong>Panel Surya Polycrystalline 5 WP (GH5P-18)</strong> — panel surya mungil bertenaga untuk kebutuhan daya kecil sehari-hari. Cocok untuk <strong>lampu taman, charger kecil, proyek DIY/edukasi, elektronik kecil, dan aki kecil</strong>. Ringan (hanya 1 kg) dan mudah dipasang di mana saja.</p>
<h4>Keunggulan</h4>
<ul>
<li>☀️ <strong>Daya 5 Watt</strong> — pas untuk beban kecil &amp; pengisian perlahan aki/baterai kecil.</li>
<li>🔩 <strong>Ringkas &amp; ringan</strong> — 180 × 270 × 17 mm, bobot 1 kg, gampang dibawa &amp; dipasang.</li>
<li>🧱 <strong>Sel Polycrystalline (Poly-Si)</strong> — andal &amp; ekonomis untuk aplikasi ringan.</li>
<li>🌡️ <strong>Tahan cuaca</strong> — suhu operasi -40°C hingga +85°C, Application Class A.</li>
<li>🔌 Cocok dipadukan dengan solar charge controller kecil untuk sistem mini.</li>
</ul>
<p><em>Ideal untuk pemula, praktikum, hobi elektronik, dan kebutuhan tenaga surya skala kecil.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>GH5P-18</td></tr>
<tr><th>Tipe Sel</th><td>Polycrystalline (Poly-Si)</td></tr>
<tr><th>Daya Maksimum (Pm)</th><td>5 W</td></tr>
<tr><th>Toleransi</th><td>0 ~ +5%</td></tr>
<tr><th>Tegangan pada Pmax (Vmp)</th><td>9 V</td></tr>
<tr><th>Arus pada Pmax (Imp)</th><td>0,55 A</td></tr>
<tr><th>Tegangan Rangkaian Terbuka (Voc)</th><td>11,6 V</td></tr>
<tr><th>Arus Hubung Singkat (Isc)</th><td>0,62 A</td></tr>
<tr><th>NOCT</th><td>45 ± 2°C</td></tr>
<tr><th>Maximum Series Fuse Rating</th><td>10 A</td></tr>
<tr><th>Suhu Operasi</th><td>-40°C hingga +85°C</td></tr>
<tr><th>Application Class</th><td>Class A</td></tr>
<tr><th>Dimensi</th><td>180 × 270 × 17 mm</td></tr>
<tr><th>Berat</th><td>1 kg</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'panel-surya-poly-5wp-gh5p-18'],
            [
                'sku' => 'PANEL-POLY-5WP-GH5P18',
                'name' => 'Panel Surya Polycrystalline 5 WP (GH5P-18)',
                'category_id' => $category?->id,
                'model' => 'GH5P-18',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Panel surya poly mungil 5 Watt (GH5P-18) untuk lampu taman, charger kecil, dan proyek DIY/edukasi. Ringan 1 kg, tahan cuaca.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 84000,
                'unit' => 'pcs',
                'weight_grams' => 1000,
                'length_cm' => 27,
                'width_cm' => 18,
                'height_cm' => 1.7,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Panel Surya Polycrystalline 5 WP GH5P-18 — Panel Mini 5 Watt',
                'meta_description' => 'Jual Panel Surya Polycrystalline 5 WP (GH5P-18), 9V/0,55A, tahan cuaca -40~85°C. Rp 84.000. Cocok untuk lampu taman, charger kecil, & proyek DIY.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 20);
        }

        $this->command?->info('Produk Panel Surya Poly 5 WP '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga jual Rp 84.000 • Stok awal 20 pcs. Upload gambar lewat Admin → Produk → Edit.');
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
