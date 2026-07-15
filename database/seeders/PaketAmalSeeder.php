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
 * Seeds the "Paket Amal" PLTS package as a VARIABLE product with three variants
 * (AMAL 2000 / 4000 / 8000), each with its own price & stock. Idempotent.
 * Product & per-variant images uploaded manually via admin.
 */
class PaketAmalSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'paket-plts')->first();

        $description = <<<'HTML'
<p><strong>Paket PLTS Amal — Anti Mati Lampu</strong><br>Paket komplit tenaga surya: tinggal pasang, langsung nyala.</p>
<p>Hemat listrik harian + backup otomatis saat PLN padam. Pilih paket sesuai kebutuhan rumah Anda. Setiap paket sudah lengkap: Panel Surya, Inverter Off-Grid, Baterai Lithium, Panel Proteksi, Kabel &amp; Aksesoris, dan Mounting Panel.</p>
<h4>Pilihan Paket</h4>
<ul>
<li><strong>AMAL 2000</strong> — 1.000 Wp panel, baterai 2,0 kWh (24V 80Ah), inverter 1.200 W. Cocok backup lampu, TV, charger, pompa ringan.</li>
<li><strong>AMAL 4000</strong> (Paling Laris) — 1.500 Wp, baterai 4,0 kWh (24V 160Ah), inverter 1.600 W. Tambah kulkas, rice cooker, kerja dari rumah.</li>
<li><strong>AMAL 8000</strong> — 3.000 Wp, baterai 8,0 kWh (24V 160Ah x2), inverter 3.000 W. Tambah AC + dapur listrik, beban lebih besar.</li>
</ul>
<h4>Keunggulan</h4>
<ul>
<li>⚡ Auto switch — otomatis nyala saat PLN mati</li>
<li>☀️ Hemat — listrik gratis dari matahari</li>
<li>🛡️ Aman — perlindungan lengkap &amp; terpercaya</li>
<li>🔋 Baterai Lithium LiFePO₄ siklus panjang + BMS</li>
<li>🧰 Panel Monocrystalline efisiensi tinggi (IP65/IP67)</li>
<li>✅ Garansi 2 tahun • Konsultasi gratis</li>
</ul>
<p><em>Biaya instalasi terpisah, disesuaikan kompleksitas pemasangan. Survey lokasi berbayar.</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>AMAL 2000</th><td>1.000 Wp • 2,0 kWh (24V 80Ah) • Inverter 1.200 W — Rp 24,9 jt</td></tr>
<tr><th>AMAL 4000</th><td>1.500 Wp • 4,0 kWh (24V 160Ah) • Inverter 1.600 W — Rp 34,9 jt</td></tr>
<tr><th>AMAL 8000</th><td>3.000 Wp • 8,0 kWh (24V 160Ah x2) • Inverter 3.000 W — Rp 54,9 jt</td></tr>
<tr><th>Isi Paket</th><td>Panel Surya, Inverter Off-Grid, Baterai Lithium, Panel Proteksi, Kabel &amp; Aksesoris, Mounting</td></tr>
<tr><th>Garansi</th><td>2 Tahun</td></tr>
</tbody></table>
HTML;

        $product = Product::updateOrCreate(
            ['slug' => 'paket-amal-plts'],
            [
                'sku' => 'PAKET-AMAL',
                'name' => 'Paket PLTS Amal (Anti Mati Lampu)',
                'category_id' => $category?->id,
                'model' => 'Amal Series',
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Paket komplit PLTS anti mati lampu. Tiga pilihan: AMAL 2000/4000/8000 — panel, inverter, baterai lithium, proteksi, kabel & mounting. Garansi 2 tahun.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 24900000,   // base = cheapest variant ("mulai dari")
                'sale_price' => null,
                'unit' => 'paket',
                'weight_grams' => 80000,
                'requires_freight' => true,
                'warranty' => 'Garansi 2 tahun',
                'is_featured' => true,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => 'Paket PLTS Amal — Anti Mati Lampu (AMAL 2000/4000/8000)',
                'meta_description' => 'Paket komplit PLTS Rekasurya: panel surya, inverter off-grid, baterai lithium, proteksi & mounting. Mulai Rp 24,9 jt. Backup otomatis saat PLN padam.',
            ],
        );

        $variants = [
            ['name' => 'AMAL 2000', 'sku' => 'PAKET-AMAL-2000', 'price' => 24900000, 'wp' => '1.000 Wp', 'kwh' => '2,0 kWh', 'inv' => '1.200 W'],
            ['name' => 'AMAL 4000', 'sku' => 'PAKET-AMAL-4000', 'price' => 34900000, 'wp' => '1.500 Wp', 'kwh' => '4,0 kWh', 'inv' => '1.600 W'],
            ['name' => 'AMAL 8000', 'sku' => 'PAKET-AMAL-8000', 'price' => 54900000, 'wp' => '3.000 Wp', 'kwh' => '8,0 kWh', 'inv' => '3.000 W'],
        ];

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => $v['sku']],
                [
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'option_values' => ['Paket' => $v['name']],
                    'price' => $v['price'],
                    'sale_price' => null,
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
            $this->setVariantStock($product, $variant, 5);
        }

        $this->command?->info('Produk Paket PLTS Amal (3 varian) berhasil ditambahkan/diperbarui (slug: '.$product->slug.').');
        $this->command?->warn('Ingat: upload gambar produk + gambar tiap varian + PDF lewat Admin → Produk → Edit.');
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
