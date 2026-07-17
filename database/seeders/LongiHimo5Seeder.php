<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds the LONGi Hi-MO 5 LR5-72HBD 540M bifacial dual-glass module as a
 * CLEARANCE product (condition: Baru - Minor Defect). Struck price Rp 2,7 jt →
 * sale Rp 1,9 jt, 10 pcs, 5-year warranty, tag "JAMINAN HARGA TERMURAH".
 * Idempotent. Images/PDF uploaded via admin.
 */
class LongiHimo5Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-bifacial')->first();

        $description = <<<'HTML'
<p><strong>LONGi Hi-MO 5 LR5-72HBD 540 Wp — Bifacial Dual Glass</strong><br>Panel surya monocrystalline half-cell dari LONGi, produsen modul surya terbesar dunia. Teknologi <strong>bifacial dual glass</strong> menangkap cahaya dari sisi depan &amp; belakang untuk energi ekstra hingga +25%.</p>
<div class="rounded-lg bg-amber-50 p-3 text-amber-800">
<p><strong>⚡ CLEARANCE — Baru, Minor Defect.</strong> Unit baru dengan cacat kosmetik ringan (mis. goresan halus pada frame). Performa &amp; kelistrikan tetap normal. Harga spesial, stok terbatas.</p>
</div>
<h4>Keunggulan</h4>
<ul>
<li>☀️ <strong>Bifacial</strong> — bifaciality 70±5%, panen energi dari dua sisi.</li>
<li>🔲 <strong>Half-cell 144 sel</strong> (6×24) — rugi daya lebih kecil, tahan shading parsial.</li>
<li>🪟 <strong>Dual glass 2,0+2,0 mm</strong> heat-strengthened — anti-PID, tahan lembap &amp; korosi.</li>
<li>🌡️ Koefisien suhu Pmax −0,340%/°C, suhu operasi lebih rendah.</li>
<li>💪 Beban statis depan 5400 Pa, tahan hujan es 25 mm @ 23 m/s.</li>
<li>🔌 Junction box IP68 3 diode, DC1500V.</li>
</ul>
<h4>Garansi</h4>
<ul>
<li>Garansi produk <strong>5 tahun</strong> (khusus unit clearance).</li>
</ul>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>LONGi Hi-MO 5 LR5-72HBD-540M</td></tr>
<tr><th>Tipe</th><td>Monocrystalline Half-Cell, Bifacial Dual Glass</td></tr>
<tr><th>Daya Output (Pmax)</th><td>540 Wp</td></tr>
<tr><th>Efisiensi Modul</th><td>21,1%</td></tr>
<tr><th>Toleransi Daya</th><td>0 ~ +3%</td></tr>
<tr><th>Tegangan Sirkuit Terbuka (Voc)</th><td>49,50 V</td></tr>
<tr><th>Arus Hubung Singkat (Isc)</th><td>13,85 A</td></tr>
<tr><th>Tegangan Daya Maks (Vmp)</th><td>41,65 V</td></tr>
<tr><th>Arus Daya Maks (Imp)</th><td>12,97 A</td></tr>
<tr><th>Bifacialitas</th><td>70 ±5%</td></tr>
<tr><th>Jumlah Sel</th><td>144 (6×24)</td></tr>
<tr><th>Kaca</th><td>Dual glass 2,0+2,0 mm heat-strengthened</td></tr>
<tr><th>Frame</th><td>Aluminium anodized</td></tr>
<tr><th>Dimensi</th><td>2256 × 1133 × 35 mm</td></tr>
<tr><th>Berat</th><td>32,3 kg</td></tr>
<tr><th>Junction Box</th><td>IP68, 3 diode</td></tr>
<tr><th>Tegangan Sistem Maks</th><td>DC 1500 V (IEC/UL)</td></tr>
<tr><th>Sekring Seri Maks</th><td>30 A</td></tr>
<tr><th>Beban Statis Maks</th><td>Depan 5400 Pa • Belakang 2400 Pa</td></tr>
<tr><th>Koef. Suhu Pmax</th><td>−0,340%/°C</td></tr>
<tr><th>Suhu Operasi</th><td>−40°C – +85°C</td></tr>
<tr><th>Kondisi</th><td>Baru - Minor Defect (Clearance)</td></tr>
<tr><th>Garansi</th><td>5 tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'panel-surya-longi-himo5-540wp-clearance'],
            [
                'sku' => 'LONGI-HIMO5-540-CLR',
                'name' => 'Panel Surya LONGi Hi-MO 5 540 Wp Bifacial (Clearance)',
                'category_id' => $category?->id,
                'model' => 'LR5-72HBD-540M',
                'product_type' => 'simple',
                'condition' => 'new_minor_defect',
                'short_description' => 'LONGi Hi-MO 5 540 Wp bifacial dual glass. CLEARANCE — baru, minor defect. Performa normal, harga spesial. Garansi 5 tahun. Stok terbatas.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 2700000,        // Harga coret (struck)
                'sale_price' => 1900000,   // Harga jual (diskon)
                'unit' => 'pcs',
                'weight_grams' => 32300,
                'length_cm' => 225.6,
                'width_cm' => 113.3,
                'height_cm' => 3.5,
                'requires_freight' => true,
                'warranty' => 'Garansi 5 tahun',
                'badge_text' => 'Paling Murah!',
                'is_clearance' => true,
                'is_promo' => true,
                'is_featured' => true,
                'is_new' => false,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'LONGi Hi-MO 5 540 Wp Bifacial Clearance — Jaminan Harga Termurah',
                'meta_description' => 'Panel surya LONGi Hi-MO 5 LR5-72HBD 540 Wp bifacial dual glass, clearance (baru minor defect). Rp 1,9 jt dari Rp 2,7 jt. Garansi 5 tahun. Stok terbatas 10 pcs.',
            ],
        );

        // Only seed the opening stock for a NEW product. On re-runs (deploys) the
        // product already exists, so we must not reset stock that admin has adjusted.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, 10);
        }

        $this->command?->info('Produk LONGi Hi-MO 5 540 Wp (Clearance) '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 2.700.000 → Rp 1.900.000 • Stok 10 pcs • Tag: Paling Murah!.');
        $this->command?->warn('Ingat: upload gambar produk + PDF datasheet lewat Admin → Produk → Edit.');
    }

    private function setStock(Product $product, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, null, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder)');
        }
    }
}
