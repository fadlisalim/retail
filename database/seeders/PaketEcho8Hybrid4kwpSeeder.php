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
 * Paket PLTS Hybrid: Aurora ECHO-8 (all-in-one 4.000VA / 8 kWh, MPPT 5.000W)
 * + solar panel total 4 kWp + mounting + panel proteksi AC/DC + kabel PV 30 m.
 * Harga paket Rp 64.900.000 (exc instalasi), garansi paket 3 tahun.
 * Idempotent (firstOrCreate). Stok awal 5 = placeholder, sesuaikan di Admin.
 */
class PaketEcho8Hybrid4kwpSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'paket-plts-hybrid')->first();
        $rumah = Category::where('slug', 'paket-plts-rumah')->first();
        $brand = Brand::firstOrCreate(['slug' => 'aurora'], ['name' => 'Aurora', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Paket PLTS Hybrid siap pasang</strong> berbasis <strong>Aurora ECHO-8 All-in-One Solar Generator</strong> — inverter 4.000VA, baterai LiFePO4 8 kWh, dan MPPT hingga 5.000W dalam satu tower ber-roda — dipasangkan dengan <strong>solar panel total 4.000 Wp</strong>. Hemat tagihan PLN di siang hari, tetap terang saat mati lampu.</p>

<h4>Isi Paket</h4>
<ul>
<li>🔋 <strong>1× Aurora ECHO-8</strong> — all-in-one 4.000VA / 8 kWh LiFePO4 (≥8.000 siklus, DOD 90%, smart BMS).</li>
<li>☀️ <strong>Solar panel total 4.000 Wp</strong> (jumlah lembar &amp; merek high-output menyesuaikan ketersediaan stok — dikonfirmasi admin saat pemesanan).</li>
<li>🔩 <strong>Mounting/rangka atap</strong> lengkap.</li>
<li>⚡ <strong>Panel proteksi AC &amp; DC</strong> (MCB/SPD) — sistem aman sesuai standar.</li>
<li>🔌 <strong>Kabel PV 30 meter</strong> + konektor.</li>
</ul>

<h4>Estimasi Energi</h4>
<ul>
<li>Produksi surya: <strong>±14–16 kWh/hari</strong> (4 kWp × 3,5–4 jam matahari efektif, tergantung lokasi &amp; cuaca).</li>
<li>Cadangan baterai: <strong>8 kWh</strong> (terpakai ±7,2 kWh @DOD 90%) — kulkas, lampu, WiFi, TV &amp; pompa bisa tetap jalan saat PLN padam.</li>
<li>Output <strong>4.000VA Pure Sine Wave</strong> — cukup untuk hampir seisi rumah.</li>
</ul>

<h4>Kenapa Paket Ini?</h4>
<ul>
<li>✅ Komponen dijamin <strong>kompatibel</strong> — tanpa pusing hitung satu-satu.</li>
<li>🛡️ <strong>Garansi paket 3 tahun</strong>, after-sales by Rekasurya.</li>
<li>🏠 Sistem <strong>hybrid</strong>: pakai surya + PLN secara pintar, otomatis backup saat padam.</li>
</ul>

<p><em>Harga <strong>belum termasuk instalasi</strong> — tim Rekasurya siap survei &amp; pasang (biaya sesuai lokasi/kondisi atap, minta penawaran instalasi ke admin). Sebelum order, chat admin dulu untuk konfirmasi stok &amp; jadwal.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Unit Utama</th><td>Aurora ECHO-8 All-in-One Solar Generator (tower ber-roda)</td></tr>
<tr><th>Inverter</th><td>4.000VA · Pure Sine Wave · 220/230Vac single-phase</td></tr>
<tr><th>Baterai</th><td>LiFePO4 8 kWh · 314 Ah · ≥8.000 siklus · DOD 90% · smart BMS</td></tr>
<tr><th>Solar Charger</th><td>MPPT 120–500VDC · input PV hingga 5.000W</td></tr>
<tr><th>Array Panel Surya</th><td>Total 4.000 Wp (merek/jumlah lembar dikonfirmasi admin)</td></tr>
<tr><th>Estimasi Produksi</th><td>±14–16 kWh/hari (tergantung lokasi &amp; cuaca)</td></tr>
<tr><th>Kelengkapan</th><td>Mounting/rangka atap, panel proteksi AC &amp; DC (MCB/SPD), kabel PV 30 m + konektor</td></tr>
<tr><th>Tipe Sistem</th><td>Hybrid (surya + PLN, backup otomatis saat padam)</td></tr>
<tr><th>Instalasi</th><td>Belum termasuk (tersedia oleh tim Rekasurya — minta penawaran)</td></tr>
<tr><th>Garansi</th><td>Paket 3 Tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'paket-plts-hybrid-aurora-echo-8-solar-4kwp'],
            [
                'sku' => 'PAKET-ECHO8-4KWP',
                'name' => 'Paket PLTS Hybrid All-in-One Aurora ECHO-8 + Solar Panel 4 kWp',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => 'Paket PLTS hybrid siap pasang: Aurora ECHO-8 (4.000VA / 8 kWh LiFePO4, MPPT 5.000W) + solar panel 4 kWp + mounting + panel proteksi AC/DC + kabel PV 30 m. Garansi paket 3 tahun. Harga exc instalasi.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 64900000,
                'unit' => 'paket',
                'weight_grams' => 340000,
                'requires_freight' => true,
                'warranty' => 'Garansi Paket 3 Tahun',
                'keywords' => 'paket plts hybrid, echo-8, aurora, 4 kwp, all in one, solar generator, plts rumah, anti mati lampu, 8 kwh, paket panel surya',
                'is_new' => true,
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Paket PLTS Hybrid Aurora ECHO-8 + Solar Panel 4 kWp — Rp 64,9 Juta (exc instalasi)',
                'meta_description' => 'Paket PLTS hybrid Aurora ECHO-8 4.000VA/8kWh + panel surya 4 kWp, lengkap mounting, proteksi AC/DC & kabel PV 30m. Produksi ±14-16 kWh/hari. Garansi 3 tahun. Rp 64.900.000.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            $product->categories()->sync(array_values(array_filter([$category?->id, $rumah?->id])));
            $this->setStock($product, 5); // placeholder — sesuaikan di Admin
        }

        // Paket ±340 kg wajib jalur kargo (ongkir dikonfirmasi), bukan kurir
        // reguler — dilengkapi juga untuk baris yang sudah ada di produksi.
        if (! $product->requires_freight) {
            $product->forceFill(['requires_freight' => true])->save();
        }

        $this->command?->info('Paket ECHO-8 Hybrid 4kWp '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga Rp 64.900.000 (exc instalasi) • Garansi paket 3 tahun • Stok awal 5 (placeholder). Upload foto lewat Admin → Produk.');
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
