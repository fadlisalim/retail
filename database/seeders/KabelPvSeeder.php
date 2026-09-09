<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Kabel PV DC solar — SATU produk dengan varian penampang, dijual PER METER
 * (qty di keranjang = jumlah meter), hanya tersedia warna hitam:
 *  - Varian 1×4mm²  → Rp 18.000/m (modal 14.000 → margin 22,2%)
 *  - Varian 1×6mm²  → Rp 24.000/m (modal 19.000 → margin 20,8%)
 *
 * Versi awal seeder ini membuat 2 produk terpisah; run() mengkonversinya:
 * produk lama tanpa pesanan dihapus (soft delete) dan stoknya dipindah ke
 * varian; yang sudah punya pesanan hanya diarsipkan supaya riwayat aman.
 * Idempotent — aman dijalankan ulang, tidak menimpa editan admin.
 */
class KabelPvSeeder extends Seeder
{
    private const VARIANTS = [
        [
            'legacy_slug' => 'kabel-pv-dc-solar-1x4mm-hitam-per-meter',
            'sku' => 'KBL-PV-4MM-BLK',
            'name' => '1×4mm²',
            'size' => '4mm²',
            'price' => 18000,
            'cost' => 14000,
            'weight_grams' => 60, // ± per meter (estimasi)
            'sort' => 0,
        ],
        [
            'legacy_slug' => 'kabel-pv-dc-solar-1x6mm-hitam-per-meter',
            'sku' => 'KBL-PV-6MM-BLK',
            'name' => '1×6mm²',
            'size' => '6mm²',
            'price' => 24000,
            'cost' => 19000,
            'weight_grams' => 85, // ± per meter (estimasi)
            'sort' => 1,
        ],
    ];

    public function run(): void
    {
        $category = Category::where('slug', 'kabel-konektor-proteksi-kabel-pv')->first()
            ?? Category::where('slug', 'kabel-konektor-proteksi')->first();

        $description = <<<'HTML'
<p><strong>Kabel PV DC Solar Hitam (Per Meter)</strong><br>Kabel khusus instalasi panel surya (PV) DC, inti tunggal, warna <strong>hitam</strong>. Pilih penampang <strong>4mm²</strong> atau <strong>6mm²</strong> di pilihan varian. Dijual <strong>per meter</strong> — jumlah di keranjang = panjang kabel dalam meter (mis. qty 25 = 25 meter, dikirim menyambung bila stok roll memungkinkan).</p>
<h4>Cocok Untuk</h4>
<ul>
<li>☀️ Sambungan panel surya ke SCC/inverter.</li>
<li>🔌 Dipasangkan dengan konektor MC4 (dijual terpisah).</li>
</ul>
<h4>Tips Memilih Penampang</h4>
<ul>
<li>Gunakan <strong>4mm²</strong> untuk string arus kecil dan tarikan pendek.</li>
<li>Gunakan <strong>6mm²</strong> untuk arus lebih besar atau tarikan panjang agar rugi tegangan (voltage drop) tetap kecil.</li>
</ul>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Jenis</th><td>Kabel PV DC (solar)</td></tr>
<tr><th>Penampang</th><td>1 × 4mm² atau 1 × 6mm² (pilih varian)</td></tr>
<tr><th>Warna</th><td>Hitam (hanya tersedia hitam)</td></tr>
<tr><th>Satuan Jual</th><td>Per meter (qty = jumlah meter)</td></tr>
<tr><th>Berat</th><td>± 60 g/m (4mm²) · ± 85 g/m (6mm²) — estimasi</td></tr>
<tr><th>Spesifikasi Teknis Rinci</th><td>Menyusul dari pabrikan (rating tegangan, bahan isolasi, sertifikasi)</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'kabel-pv-dc-solar-hitam-per-meter'],
            [
                'sku' => 'KBL-PV-BLK',
                'name' => 'Kabel PV DC Solar Hitam (Per Meter)',
                'category_id' => $category?->id,
                'model' => 'PV 1×4mm² / 1×6mm²',
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Kabel PV DC solar inti tunggal warna hitam untuk instalasi panel surya — pilih varian 4mm² atau 6mm². Dijual per meter (qty di keranjang = jumlah meter).',
                'description' => $description,
                'specifications' => $specifications,
                'price' => 18000,       // "mulai dari" = varian termurah (4mm²)
                'cost_price' => 14000,  // modal varian dasar 4mm² (6mm²: 19.000, dikunci di test)
                'unit' => 'meter',
                'weight_grams' => 60,
                'length_cm' => 15,
                'width_cm' => 15,
                'height_cm' => 2,
                'requires_freight' => false,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'keywords' => 'kabel pv, kabel solar, kabel dc panel surya, kabel pv 4mm, kabel pv 6mm, kabel surya hitam, kabel mc4, kabel plts per meter',
                'meta_title' => 'Kabel PV DC Solar Hitam 4mm² / 6mm² — Per Meter',
                'meta_description' => 'Jual kabel PV DC solar hitam 1×4mm² (Rp 18.000/m) dan 1×6mm² (Rp 24.000/m) untuk instalasi panel surya. Harga per meter, kirim ke seluruh Indonesia.',
            ],
        );

        foreach (self::VARIANTS as $row) {
            $variant = $product->variants()->firstOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'option_values' => ['Penampang' => $row['size']],
                    'price' => $row['price'],
                    'cost_price' => $row['cost'],
                    'weight_grams' => $row['weight_grams'],
                    'is_active' => true,
                    'sort_order' => $row['sort'],
                ],
            );

            // Modal per varian ditambahkan belakangan — isi bila masih kosong.
            if (! $variant->wasRecentlyCreated && $variant->cost_price === null) {
                $variant->forceFill(['cost_price' => $row['cost']])->save();
            }

            // Konversi produk lama (2 produk terpisah dari versi awal seeder).
            if ($variant->wasRecentlyCreated) {
                $this->absorbLegacyProduct($row['legacy_slug'], $product, $variant);
            }
        }

        $this->command?->info('Kabel PV DC Solar Hitam (varian 4mm² & 6mm²) siap — Rp 18.000 / Rp 24.000 per meter.');
        $this->command?->warn('Cek stok varian di Admin → Stok dan upload foto produk via Admin → Produk → Edit.');
    }

    /**
     * Pindahkan stok produk lama ke varian baru, lalu bereskan produk lamanya:
     * tanpa pesanan → dihapus (soft delete); pernah dipesan → diarsipkan agar
     * riwayat pesanan tetap utuh.
     */
    private function absorbLegacyProduct(string $legacySlug, Product $parent, ProductVariant $variant): void
    {
        $legacy = Product::where('slug', $legacySlug)->first();
        if (! $legacy) {
            return;
        }

        $stock = (int) WarehouseStock::where('product_id', $legacy->id)->sum('quantity_available');
        if ($stock > 0) {
            app(StockService::class)->adjust($parent, $variant, $stock, StockMovementType::Purchase, note: 'Migrasi stok dari produk lama '.$legacySlug);
        }

        $hasOrders = DB::table('order_items')->where('product_id', $legacy->id)->exists();
        if ($hasOrders) {
            $legacy->forceFill(['status' => 'archived'])->save();
            $this->command?->warn($legacySlug.' diarsipkan (punya riwayat pesanan); stok '.$stock.' dipindah ke varian.');
        } else {
            $legacy->delete();
            $this->command?->info($legacySlug.' dihapus, stok '.$stock.' meter dipindah ke varian '.$variant->name.'.');
        }
    }
}
