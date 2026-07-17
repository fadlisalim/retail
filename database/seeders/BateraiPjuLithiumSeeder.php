<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Baterai Lithium PJU Tenaga Surya 12,8V 60Ah — paket baterai untuk lampu PJU
 * tenaga surya (otomatis nyala/mati), sudah termasuk Solar Charge Controller +
 * LED Driver. Kondisi: Baru - Sisa Proyek (Clearance), garansi 6 bulan.
 * Harga coret Rp 2,7 jt → clearance Rp 1,95 jt. Tag "Termurah!".
 *
 * Idempotent (firstOrCreate): aman dijalankan ulang — tidak menimpa produk yang
 * sudah diedit admin, dan hanya mengisi stok awal saat produk pertama dibuat.
 * Gambar produk diupload via Admin → Produk → Edit.
 */
class BateraiPjuLithiumSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'baterai')->first();

        $description = <<<'HTML'
<p><strong>Baterai Lithium PJU Tenaga Surya 12,8V 60Ah</strong><br>Paket baterai lengkap untuk lampu PJU (Penerangan Jalan Umum) tenaga surya. <strong>Sudah termasuk Solar Charge Controller + LED Driver</strong> dalam satu unit, jadi tinggal sambung panel surya dan lampu LED — lampu otomatis <strong>nyala saat malam &amp; mati saat siang</strong>.</p>
<div class="rounded-lg bg-amber-50 p-3 text-amber-800">
<p><strong>⚡ CLEARANCE — Barang Baru, Sisa Proyek.</strong> Unit baru (belum terpakai) sisa pengadaan proyek. Garansi toko 6 bulan. Harga spesial, stok terbatas.</p>
</div>
<h4>Cocok Untuk</h4>
<ul>
<li>💡 Lampu PJU / jalan tenaga surya otomatis (auto on/off).</li>
<li>🔆 Panel surya input <strong>maksimal 21V / 15A</strong>.</li>
<li>💡 Lampu LED beban <strong>maksimal 60W</strong>.</li>
</ul>
<h4>Keunggulan</h4>
<ul>
<li>🔋 Baterai Lithium berkualitas — aman &amp; tahan lama.</li>
<li>🌱 Ramah lingkungan, stabil di suhu tinggi.</li>
<li>🧰 Sudah termasuk <strong>kabel PV</strong> dan <strong>kabel ke LED</strong> — siap pasang.</li>
<li>📦 Bodi aluminium ringkas &amp; kokoh.</li>
</ul>
<h4>Garansi</h4>
<ul>
<li>Garansi toko <strong>6 bulan</strong>.</li>
</ul>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Tipe Baterai</th><td>Lithium 12,8V 60Ah</td></tr>
<tr><th>Termasuk</th><td>Solar Charge Controller + LED Driver</td></tr>
<tr><th>Input Panel Surya</th><td>Maksimal 21V / 15A</td></tr>
<tr><th>Output Lampu LED</th><td>Maksimal 60W</td></tr>
<tr><th>Dimensi</th><td>36 × 15 × 10 cm</td></tr>
<tr><th>Berat</th><td>6 kg</td></tr>
<tr><th>Kelengkapan</th><td>Unit baterai + kabel PV + kabel ke LED</td></tr>
<tr><th>Mode Kerja</th><td>Otomatis nyala malam / mati siang</td></tr>
<tr><th>Kondisi</th><td>Baru - Sisa Proyek (Clearance)</td></tr>
<tr><th>Garansi</th><td>6 bulan (garansi toko)</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'baterai-lithium-pju-tenaga-surya-128v-60ah-clearance'],
            [
                'sku' => 'BAT-PJU-128-60AH-CLR',
                'name' => 'Baterai Lithium PJU Tenaga Surya 12,8V 60Ah (Clearance)',
                'category_id' => $category?->id,
                'model' => '12,8V 60Ah',
                'product_type' => 'simple',
                'condition' => 'new_project_surplus',
                'short_description' => 'Baterai Lithium 12,8V 60Ah untuk PJU tenaga surya otomatis. Sudah termasuk Solar Charge Controller + LED Driver, kabel PV & kabel LED. Baru sisa proyek, garansi 6 bulan.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 2700000,        // Harga coret
                'sale_price' => 1950000,   // Harga clearance
                'unit' => 'unit',
                'weight_grams' => 6000,
                'length_cm' => 36,
                'width_cm' => 15,
                'height_cm' => 10,
                'requires_freight' => false,
                'warranty' => 'Garansi toko 6 bulan',
                'badge_text' => 'Termurah!',
                'is_clearance' => true,
                'is_promo' => true,
                'is_featured' => true,
                'is_new' => false,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Baterai Lithium PJU Tenaga Surya 12,8V 60Ah — Clearance Termurah',
                'meta_description' => 'Baterai Lithium 12,8V 60Ah untuk PJU tenaga surya otomatis, termasuk Solar Charge Controller + LED Driver. Baru sisa proyek, garansi 6 bulan. Rp 1,95 jt dari Rp 2,7 jt.',
            ],
        );

        // Kondisi "Baru - Sisa Proyek" — detail kondisi untuk halaman produk.
        $stockTarget = 20;
        $product->conditionDetail()->firstOrCreate([], [
            'reason_for_sale' => 'Barang baru sisa pengadaan proyek PJU. Belum terpakai.',
            'item_location' => 'Gudang Jakarta',
            'available_quantity' => $stockTarget,
            'purchase_year' => (int) now()->year,
            'remaining_warranty' => 'Garansi toko 6 bulan',
            'completeness' => 'Unit baterai + Solar Charge Controller + LED Driver + kabel PV + kabel ke LED',
            'defect_notes' => 'Tidak ada cacat fungsi. Baru, sisa proyek.',
            'is_returnable' => true,
            'is_negotiable' => false,
            'pickup_required' => false,
            'auto_shipping' => true,
        ]);

        // Stok awal hanya untuk produk yang baru dibuat — jangan reset stok admin saat re-run.
        if ($product->wasRecentlyCreated) {
            $this->setStock($product, $stockTarget);
        }

        $this->command?->info('Produk Baterai Lithium PJU 12,8V 60Ah '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' (slug: '.$product->slug.').');
        $this->command?->warn('Harga: Rp 2.700.000 → Rp 1.950.000 • Stok awal '.$stockTarget.' • Tag: Termurah! • Kondisi: Baru - Sisa Proyek.');
        $this->command?->warn('Ingat: upload gambar produk lewat Admin → Produk → Edit, dan sesuaikan stok bila perlu.');
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
