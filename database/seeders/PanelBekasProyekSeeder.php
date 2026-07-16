<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds a used (project-surplus) polycrystalline solar panel as a VARIABLE
 * product with two wattage variants: 50 Wp (Rp 170.000) and 100 Wp
 * (Rp 500.000). No brand (unbranded surplus), condition "Bekas Pakai",
 * 1-year warranty. Stock starts at 0 — set via the Stok menu. Idempotent.
 */
class PanelBekasProyekSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-polycrystalline')->first();

        $description = <<<'HTML'
<p><strong>Panel Surya Bekas Sisa Proyek — Polycrystalline</strong><br>Modul surya polikristalin eks-proyek, kondisi bekas pakai namun masih berfungsi normal. Cocok untuk kebutuhan hemat: penerangan, pompa DC, charger aki, atau belajar/eksperimen PLTS. Pilih daya sesuai kebutuhan.</p>
<div class="rounded-lg bg-amber-50 p-3 text-amber-800">
<p><strong>♻️ Barang bekas sisa proyek.</strong> Mungkin ada bekas pemakaian/goresan kosmetik pada frame atau label. Output listrik sudah dicek berfungsi. Harga miring, stok terbatas.</p>
</div>
<h4>Pilihan Daya</h4>
<ul>
<li><strong>50 Wp</strong> — Pmax 50 W • Vmp 18,2 V • Imp 2,78 A • Voc 22,4 V • Isc 3,20 A.</li>
<li><strong>100 Wp</strong> — Pmax 100 W • Vmp 17 V • Imp 5,89 A • Voc 22,0 V • Isc 6,08 A • ±3%.</li>
</ul>
<h4>Keterangan</h4>
<ul>
<li>🔋 Tipe sel: Polycrystalline (Poly-Si).</li>
<li>🌡️ Suhu operasi −40°C s/d +85°C.</li>
<li>⚡ Tegangan sistem maksimum 1000 V.</li>
<li>✅ Garansi 1 tahun.</li>
</ul>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Kondisi</th><td>Bekas Pakai (sisa proyek)</td></tr>
<tr><th>Tipe Sel</th><td>Polycrystalline (Poly-Si)</td></tr>
<tr><th>Daya Tersedia</th><td>50 Wp / 100 Wp (pilih varian)</td></tr>
<tr><th>50 Wp</th><td>Pmax 50 W • Vmp 18,2 V • Imp 2,78 A • Voc 22,4 V • Isc 3,20 A</td></tr>
<tr><th>100 Wp</th><td>Pmax 100 W • Vmp 17 V • Imp 5,89 A • Voc 22,0 V • Isc 6,08 A • Toleransi ±3%</td></tr>
<tr><th>Dimensi 100 Wp</th><td>1020 × 670 × 30 mm • ± 12,5 kg</td></tr>
<tr><th>NOCT (100 Wp)</th><td>47 ±2°C</td></tr>
<tr><th>Sekring Seri Maks (100 Wp)</th><td>15 A</td></tr>
<tr><th>Tegangan Sistem Maks</th><td>1000 V</td></tr>
<tr><th>Suhu Operasi</th><td>−40°C – +85°C</td></tr>
<tr><th>Garansi</th><td>1 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::updateOrCreate(
            ['slug' => 'panel-surya-bekas-sisa-proyek'],
            [
                'sku' => 'PANEL-BEKAS-POLY',
                'name' => 'Panel Surya Bekas Sisa Proyek (50 Wp / 100 Wp)',
                'category_id' => $category?->id,
                'brand_id' => null,   // tanpa merk
                'model' => null,
                'product_type' => 'variable',
                'condition' => 'used',
                'short_description' => 'Panel surya polycrystalline bekas sisa proyek. Pilih 50 Wp atau 100 Wp. Kondisi berfungsi normal, harga miring, garansi 1 tahun. Stok terbatas.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 170000,     // base = varian termurah (50 Wp)
                'sale_price' => null,
                'unit' => 'pcs',
                'weight_grams' => 8000,
                'length_cm' => 102,
                'width_cm' => 67,
                'height_cm' => 3,
                'requires_freight' => false,
                'warranty' => 'Garansi 1 tahun',
                'is_clearance' => true,
                'is_featured' => false,
                'is_new' => false,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Panel Surya Bekas Sisa Proyek 50 Wp / 100 Wp — Harga Miring',
                'meta_description' => 'Jual panel surya polycrystalline bekas sisa proyek, 50 Wp (Rp 170.000) & 100 Wp (Rp 500.000). Berfungsi normal, garansi 1 tahun. Stok terbatas.',
            ],
        );

        // Per-variant weight & dimensions for accurate shipping. 100 Wp taken from
        // the module label (12,5 kg / 1020×670×30 mm); 50 Wp estimated (adjustable).
        $variants = [
            ['name' => '50 Wp', 'sku' => 'PANEL-BEKAS-50', 'price' => 170000, 'weight' => 5000, 'l' => 67, 'w' => 54, 'h' => 3],
            ['name' => '100 Wp', 'sku' => 'PANEL-BEKAS-100', 'price' => 500000, 'weight' => 12500, 'l' => 102, 'w' => 67, 'h' => 3],
        ];

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => $v['sku']],
                [
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'option_values' => ['Daya' => $v['name']],
                    'price' => $v['price'],
                    'sale_price' => null,
                    'weight_grams' => $v['weight'],
                    'length_cm' => $v['l'],
                    'width_cm' => $v['w'],
                    'height_cm' => $v['h'],
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
            // Stok mulai 0 — atur lewat menu Stok.
            $this->setVariantStock($product, $variant, 0);
        }

        $this->command?->info('Produk Panel Surya Bekas Sisa Proyek (2 varian) berhasil ditambahkan/diperbarui (slug: '.$product->slug.').');
        $this->command?->warn('Harga: 50 Wp = Rp 170.000 • 100 Wp = Rp 500.000 • Tanpa merk • Kondisi Bekas Pakai • Garansi 1 tahun.');
        $this->command?->warn('Stok masih 0 — set jumlah tiap varian lewat Admin → Stok. Upload gambar lewat Admin → Produk → Edit.');
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
