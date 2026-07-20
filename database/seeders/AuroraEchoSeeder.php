<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Seeds Aurora Echo — power station portable LiFePO4, product_type variable
 * dengan 2 varian:
 *   Echo-1 · 1000Wh / 500W  — Rp 7.900.000 → Rp 6.700.000, ±13 kg
 *   Echo-2 · 2000Wh / 1000W — Rp 13.900.000 → Rp 11.300.000, ±21 kg
 * Stok tiap varian 5. Idempotent (firstOrCreate + updateOrCreate).
 */
class AuroraEchoSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-power-station')->first();
        $brand = Brand::firstOrCreate(['slug' => 'aurora'], ['name' => 'Aurora', 'is_active' => true]);

        $description = <<<'HTML'
<p><strong>Aurora Echo</strong> — listrik portable untuk camping, campervan, outdoor, jualan, sampai backup mati lampu. <strong>Tanpa BBM, tanpa berisik.</strong> Bisa di-charge dari PLN atau panel surya. Baterai <strong>LiFePO4 awet (≥8.000 siklus)</strong> dengan output <strong>Pure Sine Wave</strong> yang aman untuk elektronik sensitif.</p>
<h4>Cocok untuk situasi nyata</h4>
<ul>
<li>⛺ <strong>Camping malam</strong> — lampu, HP, speaker, kamera, router tetap jalan tanpa genset.</li>
<li>🚐 <strong>Campervan & vanlife</strong> — masak ringan, charge perangkat, trip multi-hari.</li>
<li>🛒 <strong>UMKM & jualan outdoor</strong> — lampu, mesin kecil, display, perangkat kasir.</li>
<li>🏠 <strong>Backup mati lampu</strong> — TV, lampu, laptop tetap menyala saat PLN padam.</li>
</ul>
<h4>Keunggulan</h4>
<ul>
<li>🤫 <strong>Senyap ≤30 dB</strong> — tanpa mesin & BBM, nyaman untuk indoor/outdoor.</li>
<li>☀️ <strong>Solar-ready</strong> — jemur panel siang, pakai listrik malam. Refill gratis dari matahari.</li>
<li>🌡️ <strong>Tahan -10°C s/d +40°C</strong> — dari dinginnya Bromo sampai pesisir Bali.</li>
<li>🛡️ <strong>LiFePO4 + BMS multi-lapis</strong> — lebih stabil & tahan goncangan dibanding Li-ion biasa.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — arus bersih, aman untuk laptop, kamera, alat medis.</li>
<li>✅ <strong>Garansi 2 tahun</strong>, sertifikasi EN-IEC, after-sales by Rekasurya.</li>
</ul>
<h4>Pilih Unit (Varian)</h4>
<ul>
<li><strong>Echo-1 · 1000Wh / 500W</strong> — camping, outdoor, jualan/event kecil, backup ringan. Cukup untuk ±3 malam weekend camping tanpa charge ulang.</li>
<li><strong>Echo-2 · 2000Wh / 1000W</strong> — campervan, vanlife, backup rumah, beban lebih besar & durasi lebih lama (±6 malam camping ringan / ±2 hari vanlife full).</li>
</ul>
<p><em>Bingung pilih Echo-1 atau Echo-2? Konsultasi dulu — admin bantu hitung kapasitas &amp; rekomendasi unit. Harga belum termasuk ongkir; admin konfirmasi total sebelum pembayaran.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Spesifikasi</th><th>Aurora Echo-1</th><th>Aurora Echo-2</th></tr>
<tr><th>Output Daya</th><td>500 W</td><td>1000 W</td></tr>
<tr><th>Kapasitas Baterai</th><td>1000 Wh</td><td>2000 Wh</td></tr>
<tr><th>Solar Input Maks</th><td>300 W</td><td>550 W</td></tr>
<tr><th>Tipe Baterai</th><td>LiFePO4</td><td>LiFePO4</td></tr>
<tr><th>Cycle Life</th><td>≥8.000 siklus</td><td>≥8.000 siklus</td></tr>
<tr><th>Gelombang Output</th><td>Pure Sine Wave</td><td>Pure Sine Wave</td></tr>
<tr><th>Bobot</th><td>±13 kg</td><td>±21 kg</td></tr>
<tr><th>Suhu Operasi</th><td>-10°C s/d +40°C</td><td>-10°C s/d +40°C</td></tr>
<tr><th>Tingkat Kebisingan</th><td>≤30 dB</td><td>≤30 dB</td></tr>
<tr><th>Proteksi</th><td>BMS multi-lapis</td><td>BMS multi-lapis</td></tr>
<tr><th>Sertifikasi</th><td>EN-IEC</td><td>EN-IEC</td></tr>
<tr><th>Garansi</th><td>2 Tahun</td><td>2 Tahun</td></tr>
<tr><th>Cocok untuk</th><td>Camping, outdoor, backup rumah</td><td>Campervan, vanlife, full backup</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'aurora-echo-power-station'],
            [
                'sku' => 'AURORA-ECHO',
                'name' => 'Aurora Echo Power Station Portable (LiFePO4, Solar-Ready)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Power station portable Aurora Echo (LiFePO4, ≥8.000 siklus, Pure Sine Wave). Solar-ready, senyap ≤30dB. Pilih Echo-1 (1000Wh/500W) atau Echo-2 (2000Wh/1000W). Garansi 2 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 7900000,        // "mulai dari" — harga coret Echo-1
                'sale_price' => 6700000,   // harga jual Echo-1 (termurah)
                'unit' => 'unit',
                'weight_grams' => 13000,
                'warranty' => 'Garansi 2 Tahun',
                'is_new' => true,
                'is_promo' => true,
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Aurora Echo Power Station Portable — Echo-1 & Echo-2 (LiFePO4, Solar-Ready)',
                'meta_description' => 'Jual Aurora Echo power station portable LiFePO4: Echo-1 (1000Wh/500W) Rp 6.700.000, Echo-2 (2000Wh/1000W) Rp 11.300.000. Solar-ready, Pure Sine Wave, garansi 2 tahun.',
            ],
        );

        if ($product->wasRecentlyCreated) {
            $product->categories()->sync(array_filter([$product->category_id]));
        }

        $variants = [
            ['sku' => 'AURORA-ECHO-1', 'name' => 'Echo-1 · 1000Wh / 500W', 'price' => 7900000, 'sale' => 6700000, 'weight' => 13000],
            ['sku' => 'AURORA-ECHO-2', 'name' => 'Echo-2 · 2000Wh / 1000W', 'price' => 13900000, 'sale' => 11300000, 'weight' => 21000],
        ];

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => $v['sku']],
                [
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'option_values' => ['Unit' => $v['name']],
                    'price' => $v['price'],
                    'sale_price' => $v['sale'],
                    'weight_grams' => $v['weight'],
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
            $this->setVariantStock($product, $variant, 5);
        }

        $this->command?->info('Produk Aurora Echo (2 varian) '.($product->wasRecentlyCreated ? 'ditambahkan' : 'diperbarui').' (slug: '.$product->slug.').');
        $this->command?->warn('Echo-1 Rp 7,9jt→6,7jt • Echo-2 Rp 13,9jt→11,3jt • Stok tiap varian 5. Upload gambar lewat Admin.');
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
