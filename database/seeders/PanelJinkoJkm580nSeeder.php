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
 * Seeds the Jinko Solar JKM580N-72HL4-(V) 580 Wp module — N-type TOPCon,
 * mono-facial, 144 half-cell. Semua angka diambil dari datasheet resmi
 * JKM580-605N-72HL4-(V)-F8-EN (© 2024 Jinko Solar); kolom STC yang dipakai
 * adalah kolom 580 Wp, bukan varian lain di lembar yang sama. Datasheet ini
 * tidak mencantumkan data NOCT, jadi tidak ada angka NOCT yang dicantumkan.
 * Harga jual Rp 1.900.000. Idempotent: aman dijalankan berulang.
 */
class PanelJinkoJkm580nSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first();
        $parent = Category::where('slug', 'panel-surya')->first();
        $brand = Brand::firstOrCreate(
            ['slug' => 'jinko-solar'],
            ['name' => 'Jinko Solar', 'is_active' => true],
        );

        $description = <<<'HTML'
<p><strong>Jinko Solar JKM580N-72HL4-(V)</strong> adalah modul surya <strong>580 Wp N-type TOPCon</strong> mono-facial dengan <strong>144 half-cell</strong> dan efisiensi <strong>22,45%</strong>. Jinko Solar adalah salah satu produsen modul surya terbesar di dunia, dan seri N-type ini merupakan generasi di atas panel PERC konvensional yang masih banyak beredar.</p>
<h4>Kenapa N-type, Bukan PERC Biasa?</h4>
<ul>
<li>🔋 <strong>Degradasi jauh lebih rendah</strong> — teknologi TOPCon (Tunnel Oxide Passivating Contact) menekan LID/LeTID, penyakit khas panel PERC yang membuat daya turun sejak tahun pertama.</li>
<li>📉 <strong>Penurunan tahun pertama hanya 1%</strong>, lalu <strong>0,40% per tahun</strong> — di tahun ke-30 daya masih dijamin <strong>87,4%</strong>. Panel PERC umumnya sudah di bawah 80% pada usia yang sama.</li>
<li>🌥️ <strong>Performa lebih baik saat cahaya lemah</strong> — pagi, sore, dan mendung tetap menghasilkan; cocok untuk iklim tropis Indonesia yang sering berawan.</li>
<li>🌡️ <strong>Koefisien suhu -0,29%/°C</strong> — kehilangan daya lebih kecil saat panel panas, dan di Indonesia suhu permukaan panel rutin menembus 60°C.</li>
</ul>
<h4>Keunggulan</h4>
<ul>
<li>⚡ <strong>580 Wp</strong> dengan toleransi daya <strong>0 ~ +3%</strong> — tidak pernah di bawah label, bisa lebih tinggi.</li>
<li>📊 Efisiensi modul <strong>22,45%</strong> — luas atap yang sama menghasilkan daya lebih besar.</li>
<li>💪 <strong>Tahan beban mekanis 5.400 Pa</strong> (depan, setara tekanan salju/angin ekstrem) dan <strong>2.400 Pa</strong> (belakang).</li>
<li>🧂 <strong>Tahan uap garam &amp; amonia</strong> (IEC61701 / IEC62716) — aman untuk instalasi pesisir maupun area peternakan.</li>
<li>🛡️ <strong>Jaminan anti-PID</strong>, kaca temper 3,2 mm anti-refleksi, bingkai aluminium anodized, dan junction box <strong>IP68</strong>.</li>
<li>🔗 <strong>SMBB (Super Multi Busbar)</strong> — penangkapan arus lebih merata, output dan keandalan lebih tinggi.</li>
<li>🔌 Sistem <strong>1000/1500 VDC</strong>, konektor <strong>MC4/JK03M</strong> — kompatibel dengan inverter on-grid, hybrid, maupun off-grid pada umumnya.</li>
</ul>
<h4>Cocok Untuk</h4>
<ul>
<li>PLTS rooftop rumah, ruko, dan kantor</li>
<li>PLTS komersial &amp; industri (pabrik, gudang, hotel)</li>
<li>Ground mount dan solar farm</li>
<li>Sistem off-grid/hybrid berbasis inverter tegangan tinggi (MPPT ≥ 150 VDC)</li>
</ul>
<h4>Garansi</h4>
<p><strong>12 tahun garansi produk</strong> dan <strong>30 tahun garansi performa linear</strong> dari pabrikan — salah satu jaminan terpanjang di kelasnya.</p>
<p><em>Catatan teknis: panel ini <strong>mono-facial</strong> (produksi hanya dari sisi depan). Voc 52,31 V per keping — perhatikan batas tegangan MPPT inverter Anda saat menyusun rangkaian seri, terutama pada pagi hari yang dingin ketika Voc naik. Tim kami siap membantu menghitung konfigurasi string bila diperlukan.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Merek</th><td>Jinko Solar</td></tr>
<tr><th>Model</th><td>JKM580N-72HL4-(V)</td></tr>
<tr><th>Daya Maksimum (Pmax)</th><td>580 Wp</td></tr>
<tr><th>Toleransi Daya</th><td>0 ~ +3%</td></tr>
<tr><th>Tipe Sel</th><td>N-type Mono-crystalline (TOPCon)</td></tr>
<tr><th>Tipe Modul</th><td>Mono-facial (satu sisi)</td></tr>
<tr><th>Jumlah Sel</th><td>144 (72 × 2) half-cell</td></tr>
<tr><th>Efisiensi Modul (STC)</th><td>22,45%</td></tr>
<tr><th>Tegangan Daya Maksimum (Vmp)</th><td>43,35 V</td></tr>
<tr><th>Arus Daya Maksimum (Imp)</th><td>13,38 A</td></tr>
<tr><th>Tegangan Rangkaian Terbuka (Voc)</th><td>52,31 V</td></tr>
<tr><th>Arus Hubung Singkat (Isc)</th><td>14,01 A</td></tr>
<tr><th>Koefisien Suhu Pmax</th><td>-0,29 %/°C</td></tr>
<tr><th>Koefisien Suhu Voc</th><td>-0,25 %/°C</td></tr>
<tr><th>Koefisien Suhu Isc</th><td>+0,045 %/°C</td></tr>
<tr><th>Tegangan Sistem Maksimum</th><td>1000 / 1500 VDC (IEC)</td></tr>
<tr><th>Fuse Seri Maksimum</th><td>25 A</td></tr>
<tr><th>Suhu Operasi</th><td>-40°C s/d +70°C</td></tr>
<tr><th>Dimensi</th><td>2278 × 1134 × 30 mm</td></tr>
<tr><th>Berat</th><td>27,0 kg</td></tr>
<tr><th>Kaca Depan</th><td>3,2 mm, anti-refleksi, high transmission, low iron, tempered</td></tr>
<tr><th>Bingkai</th><td>Aluminium anodized</td></tr>
<tr><th>Junction Box</th><td>IP68</td></tr>
<tr><th>Konektor</th><td>JK03M / MC4 / lainnya</td></tr>
<tr><th>Kabel Output</th><td>4,0 mm² — (+) 400 mm, (-) 200 mm</td></tr>
<tr><th>Kelas Proteksi</th><td>Class II</td></tr>
<tr><th>IEC Fire Type</th><td>Class C</td></tr>
<tr><th>Beban Mekanis</th><td>5.400 Pa (depan) / 2.400 Pa (belakang)</td></tr>
<tr><th>Degradasi Tahun Pertama</th><td>1%</td></tr>
<tr><th>Degradasi Tahunan</th><td>0,40% (daya tersisa 87,4% di tahun ke-30)</td></tr>
<tr><th>Sertifikasi</th><td>IEC61215:2021, IEC61730:2023, IEC61701 (uap garam), IEC62716 (amonia), IEC60068, IEC62804 (anti-PID), ISO9001:2015, ISO14001:2015, ISO45001:2018</td></tr>
<tr><th>Garansi</th><td>Produk 12 tahun, performa linear 30 tahun</td></tr>
<tr><th>Kondisi Pengukuran</th><td>STC: iradiasi 1000 W/m², suhu sel 25°C, AM 1.5</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'solar-panel-jinko-solar-jkm580n-72hl4-580wp-n-type-topcon'],
            [
                'sku' => 'JINKO-JKM580N-72HL4V',
                'name' => 'Solar Panel Jinko Solar 580 Wp N-type TOPCon (JKM580N-72HL4-(V))',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'JKM580N-72HL4-(V)',
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Panel surya Jinko Solar 580 Wp N-type TOPCon 144 half-cell, efisiensi 22,45%, sistem 1500V, tahan 5400Pa. Degradasi hanya 0,40%/tahun. Garansi produk 12 tahun, performa 30 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 1900000,
                'unit' => 'pcs',
                'weight_grams' => 27000,
                'length_cm' => 227.8,
                'width_cm' => 113.4,
                'height_cm' => 3.0,
                'requires_freight' => true,
                'warranty' => 'Garansi Produk 12 Tahun, Performa Linear 30 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'keywords' => 'panel surya, solar panel, jinko solar, jkm580n, 580wp, n-type, topcon, monocrystalline, half cell, 72hl4, panel plts, 1500v',
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Solar Panel Jinko Solar 580 Wp N-type TOPCon JKM580N-72HL4-(V) — Energi.Click',
                'meta_description' => 'Jual panel surya Jinko Solar 580 Wp N-type TOPCon (JKM580N-72HL4-(V)), efisiensi 22,45%, degradasi 0,40%/tahun, garansi 12/30 tahun. Rp 1.900.000.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            $product->categories()->sync(array_filter([$category?->id, $parent?->id]));
            $this->setStock($product, 10);
        }

        $this->command?->info('Produk Jinko JKM580N-72HL4-(V) '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 1.900.000 • Stok awal 10 pcs • Panel dikirim via kargo (berat 27 kg). Upload gambar lewat Admin → Produk → Edit.');
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
