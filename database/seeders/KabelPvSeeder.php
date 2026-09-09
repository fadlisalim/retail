<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Kabel PV DC solar, dijual PER METER (qty di keranjang = jumlah meter),
 * hanya tersedia warna hitam:
 *  - 1×4mm²  → Rp 18.000/m (modal 14.000 → margin 22,2%)
 *  - 1×6mm²  → Rp 24.000/m (modal 19.000 → margin 20,8%)
 *
 * Idempotent (firstOrCreate): aman dijalankan ulang, tidak menimpa produk
 * yang sudah diedit admin. Stok TIDAK di-set (isi via Admin → Stok sesuai
 * sisa roll). Gambar produk diupload via Admin → Produk → Edit.
 */
class KabelPvSeeder extends Seeder
{
    private const ROWS = [
        [
            'slug' => 'kabel-pv-dc-solar-1x4mm-hitam-per-meter',
            'sku' => 'KBL-PV-4MM-BLK',
            'name' => 'Kabel PV DC Solar 1×4mm² Hitam (Per Meter)',
            'model' => 'PV 1×4mm²',
            'size' => '4mm²',
            'price' => 18000,
            'cost' => 14000,
            'weight_grams' => 60, // ± per meter (estimasi, tembaga + isolasi)
            'guna' => 'string panel surya skala kecil–menengah dan jarak tarikan pendek–sedang',
        ],
        [
            'slug' => 'kabel-pv-dc-solar-1x6mm-hitam-per-meter',
            'sku' => 'KBL-PV-6MM-BLK',
            'name' => 'Kabel PV DC Solar 1×6mm² Hitam (Per Meter)',
            'model' => 'PV 1×6mm²',
            'size' => '6mm²',
            'price' => 24000,
            'cost' => 19000,
            'weight_grams' => 85, // ± per meter (estimasi)
            'guna' => 'arus string lebih besar atau tarikan jauh — penampang lebih besar menekan drop tegangan',
        ],
    ];

    public function run(): void
    {
        $category = Category::where('slug', 'kabel-konektor-proteksi-kabel-pv')->first()
            ?? Category::where('slug', 'kabel-konektor-proteksi')->first();

        foreach (self::ROWS as $row) {
            $description = <<<HTML
<p><strong>{$row['name']}</strong><br>Kabel khusus instalasi panel surya (PV) DC, inti tunggal {$row['size']}, warna <strong>hitam</strong>. Dijual <strong>per meter</strong> — jumlah di keranjang = panjang kabel dalam meter (mis. qty 25 = 25 meter, dikirim menyambung bila stok roll memungkinkan).</p>
<h4>Cocok Untuk</h4>
<ul>
<li>☀️ Sambungan panel surya ke SCC/inverter — {$row['guna']}.</li>
<li>🔌 Dipasangkan dengan konektor MC4 (dijual terpisah).</li>
</ul>
<h4>Tips Memilih Penampang</h4>
<ul>
<li>Gunakan <strong>4mm²</strong> untuk string arus kecil dan tarikan pendek.</li>
<li>Gunakan <strong>6mm²</strong> untuk arus lebih besar atau tarikan panjang agar rugi tegangan (voltage drop) tetap kecil.</li>
</ul>
HTML;

            $specifications = <<<HTML
<table><tbody>
<tr><th>Jenis</th><td>Kabel PV DC (solar)</td></tr>
<tr><th>Penampang</th><td>1 × {$row['size']}</td></tr>
<tr><th>Warna</th><td>Hitam (hanya tersedia hitam)</td></tr>
<tr><th>Satuan Jual</th><td>Per meter (qty = jumlah meter)</td></tr>
<tr><th>Berat</th><td>± {$row['weight_grams']} g/meter (estimasi)</td></tr>
<tr><th>Spesifikasi Teknis Rinci</th><td>Menyusul dari pabrikan (rating tegangan, bahan isolasi, sertifikasi)</td></tr>
</tbody></table>
HTML;

            $product = Product::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'category_id' => $category?->id,
                    'model' => $row['model'],
                    'product_type' => 'simple',
                    'condition' => 'new',
                    'short_description' => "Kabel PV DC solar inti tunggal {$row['size']} warna hitam untuk instalasi panel surya. Dijual per meter — qty di keranjang = jumlah meter.",
                    'description' => $description,
                    'specifications' => $specifications,
                    'price' => $row['price'],
                    'cost_price' => $row['cost'],
                    'unit' => 'meter',
                    'weight_grams' => $row['weight_grams'],
                    'length_cm' => 15,
                    'width_cm' => 15,
                    'height_cm' => 2,
                    'requires_freight' => false,
                    'is_new' => true,
                    'status' => 'published',
                    'published_at' => now(),
                    'keywords' => 'kabel pv, kabel solar, kabel dc panel surya, kabel pv '.$row['size'].', kabel surya hitam, kabel mc4, kabel plts per meter',
                    'meta_title' => $row['name'].' — Kabel Solar Per Meter',
                    'meta_description' => "Jual {$row['name']} — kabel khusus instalasi panel surya, warna hitam, harga per meter Rp ".number_format($row['price'], 0, ',', '.').'. Kirim ke seluruh Indonesia.',
                ],
            );

            $this->command?->info($row['name'].' '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').' — Rp '.number_format($row['price'], 0, ',', '.').'/meter.');
        }

        $this->command?->warn('Stok belum di-set — isi lewat Admin → Stok sesuai sisa roll, dan upload foto produk via Admin → Produk → Edit.');
    }
}
