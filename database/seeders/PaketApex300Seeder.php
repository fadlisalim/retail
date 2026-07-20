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
 * Seeds 2 paket instalasi PLTS berbasis BLUETTI Apex 300:
 *   1) Apex 300 + Solar 1200Wp (2×600) + kabel PV 30m + bracket — Rp 38.900.000
 *   2) Paket #1 + BLUETTI B300K (baterai ekspansi) — Rp 58.900.000 (+20jt / B300K)
 * Idempotent (firstOrCreate). Stok awal 5 (placeholder — sesuaikan di Admin).
 */
class PaketApex300Seeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('slug', 'portable-power-solar-generator')->first();
        $paketCategory = Category::where('slug', 'paket-plts-off-grid')->first();
        $brand = Brand::firstOrCreate(['slug' => 'bluetti'], ['name' => 'BLUETTI', 'is_active' => true]);

        // Blok info energi yang dipakai kedua paket (usable Wh berbeda).
        $energi = function (string $usableWh, string $usableKwh, ?string $extra = null): string {
            $extraLine = $extra ? "\n<li>{$extra}</li>" : '';

            return <<<HTML
<h4>⚡ Kapasitas &amp; Energi</h4>
<ul>
<li><strong>Daya beban yang bisa di-backup:</strong> hingga <strong>3.840 W</strong> (lonjakan 7.680 W) — kuat menyalakan kulkas, TV, lampu, kipas, pompa air, rice cooker, dll secara bersamaan.</li>
<li><strong>Energi tersimpan di baterai (bisa dipakai):</strong> <strong>{$usableWh} Wh (±{$usableKwh} kWh)</strong> per pengisian penuh.</li>
<li><strong>Produksi energi dari panel surya 1.200 Wp:</strong> ±<strong>4,5–5 kWh/hari</strong> (tergantung cuaca &amp; sinar matahari; asumsi 4–5 jam matahari efektif).</li>
<li><strong>Gambaran pakai:</strong> energi baterai + isi ulang surya harian cukup untuk kulkas, penerangan LED, TV, kipas, hingga pengisian gadget — cocok untuk backup listrik rumah &amp; area off-grid.{$extraLine}</li>
</ul>
<p><em>*Estimasi produksi surya bergantung lokasi &amp; cuaca. Angka baterai adalah kapasitas modul (usable).</em></p>
HTML;
        };

        // ── Paket 1: Apex 300 + Solar 1200Wp ──
        $desc1 = <<<HTML
<p><strong>Paket PLTS BLUETTI Apex 300 + Solar 1.200 Wp</strong> — solusi listrik tenaga surya siap pakai untuk backup rumah &amp; off-grid. Sudah termasuk unit, panel surya, kabel, dan bracket.</p>
<h4>📦 Isi Paket</h4>
<ul>
<li>1× <strong>BLUETTI Apex 300</strong> — power station 2.764,8 Wh / 3.840 W (LiFePO4)</li>
<li>2× <strong>Solar Panel 600 Wp</strong> (total <strong>1.200 Wp</strong>)</li>
<li><strong>Kabel PV 30 meter</strong></li>
<li><strong>Bracket / dudukan panel surya</strong></li>
</ul>
{$energi('2.764,8', '2,76')}
<p><em>Butuh kapasitas lebih besar? Tersedia paket dengan tambahan baterai BLUETTI B300K (+Rp 20.000.000 per unit, sudah termasuk kabel &amp; aksesori).</em></p>
HTML;

        $spec1 = <<<'HTML'
<table><tbody>
<tr><th>Isi Paket</th><td>Apex 300 + 2× Solar 600Wp (1.200Wp) + Kabel PV 30m + Bracket</td></tr>
<tr><th>Unit Utama</th><td>BLUETTI Apex 300 (2.764,8 Wh / 3.840 W, LiFePO4)</td></tr>
<tr><th>Daya Output (Backup)</th><td>3.840 W (lonjakan 7.680 W)</td></tr>
<tr><th>Energi Baterai (Usable)</th><td>2.764,8 Wh (±2,76 kWh)</td></tr>
<tr><th>Total Panel Surya</th><td>1.200 Wp (2 × 600 Wp)</td></tr>
<tr><th>Estimasi Produksi Surya</th><td>±4,5–5 kWh/hari (tergantung cuaca)</td></tr>
<tr><th>Input Surya Apex 300</th><td>hingga 1.200 W (12–150V, MC4)</td></tr>
<tr><th>Kabel PV</th><td>30 meter</td></tr>
<tr><th>Aksesori</th><td>Bracket / dudukan panel surya</td></tr>
</tbody></table>
HTML;

        $this->makePaket(
            slug: 'paket-bluetti-apex-300-solar-1200wp',
            sku: 'PAKET-APEX300-SOLAR1200',
            name: 'Paket PLTS BLUETTI Apex 300 + Solar 1.200 Wp (2×600) + Kabel & Bracket',
            categoryId: $category?->id,
            paketCategoryId: $paketCategory?->id,
            brandId: $brand->id,
            price: 38900000,
            description: $desc1,
            specifications: $spec1,
            shortDescription: 'Paket PLTS siap pakai: BLUETTI Apex 300 (2,76kWh/3.840W) + solar 1.200Wp (2×600) + kabel PV 30m + bracket. Backup listrik rumah & off-grid.',
            weightGrams: 85000,
        );

        // ── Paket 2: + B300K ──
        $desc2 = <<<HTML
<p><strong>Paket PLTS BLUETTI Apex 300 + Solar 1.200 Wp + Baterai B300K</strong> — kapasitas simpan energi <strong>2× lebih besar</strong> untuk backup lebih lama. Termasuk baterai ekspansi BLUETTI B300K beserta kabel &amp; aksesorinya.</p>
<h4>📦 Isi Paket</h4>
<ul>
<li>1× <strong>BLUETTI Apex 300</strong> — power station 2.764,8 Wh / 3.840 W</li>
<li>1× <strong>BLUETTI B300K</strong> — baterai ekspansi 2.764,8 Wh (termasuk kabel &amp; aksesori)</li>
<li>2× <strong>Solar Panel 600 Wp</strong> (total <strong>1.200 Wp</strong>)</li>
<li><strong>Kabel PV 30 meter</strong> + <strong>Bracket</strong> panel surya</li>
</ul>
{$energi('5.529,6', '5,53', 'Bisa terus ditambah hingga <strong>6× B300K</strong> → total hingga ±19,3 kWh. Setiap penambahan 1 B300K <strong>+Rp 20.000.000</strong> (sudah termasuk kabel &amp; aksesori).')}
HTML;

        $spec2 = <<<'HTML'
<table><tbody>
<tr><th>Isi Paket</th><td>Apex 300 + B300K + 2× Solar 600Wp (1.200Wp) + Kabel PV 30m + Bracket</td></tr>
<tr><th>Unit Utama</th><td>BLUETTI Apex 300 + baterai ekspansi B300K</td></tr>
<tr><th>Daya Output (Backup)</th><td>3.840 W (lonjakan 7.680 W)</td></tr>
<tr><th>Energi Baterai (Usable)</th><td>5.529,6 Wh (±5,53 kWh) — Apex 300 + 1× B300K</td></tr>
<tr><th>Ekspansi Maks</th><td>hingga 6× B300K → ±19,3 kWh (+Rp 20jt / B300K)</td></tr>
<tr><th>Total Panel Surya</th><td>1.200 Wp (2 × 600 Wp)</td></tr>
<tr><th>Estimasi Produksi Surya</th><td>±4,5–5 kWh/hari (tergantung cuaca)</td></tr>
<tr><th>Kabel PV</th><td>30 meter</td></tr>
<tr><th>Aksesori</th><td>Bracket / dudukan panel surya + kabel B300K</td></tr>
</tbody></table>
HTML;

        $this->makePaket(
            slug: 'paket-bluetti-apex-300-solar-1200wp-b300k',
            sku: 'PAKET-APEX300-SOLAR1200-B300K',
            name: 'Paket PLTS BLUETTI Apex 300 + Solar 1.200 Wp + Baterai B300K',
            categoryId: $category?->id,
            paketCategoryId: $paketCategory?->id,
            brandId: $brand->id,
            price: 58900000, // 38.900.000 + 20.000.000 (B300K)
            description: $desc2,
            specifications: $spec2,
            shortDescription: 'Paket PLTS: BLUETTI Apex 300 + B300K (baterai 2× lipat, ±5,53kWh) + solar 1.200Wp + kabel PV 30m + bracket. Backup listrik lebih lama.',
            weightGrams: 115000,
        );

        $this->command?->warn('2 paket Apex 300 diproses. Stok awal 5 (placeholder — sesuaikan di Admin). Upload gambar lewat Admin.');
    }

    private function makePaket(
        string $slug, string $sku, string $name, ?int $categoryId, ?int $paketCategoryId, int $brandId,
        int $price, string $description, string $specifications, string $shortDescription, int $weightGrams,
    ): void {
        $product = Product::firstOrCreate(
            ['slug' => $slug],
            [
                'sku' => $sku,
                'name' => $name,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'product_type' => 'simple',
                'condition' => 'new',
                'short_description' => $shortDescription,
                'description' => $description,
                'specifications' => $specifications,
                'price' => $price,
                'unit' => 'paket',
                'weight_grams' => $weightGrams,
                'is_new' => true,
                'is_featured' => true,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => $name,
                'meta_description' => $shortDescription,
            ],
        );

        if ($product->wasRecentlyCreated) {
            $ids = array_values(array_filter([$product->category_id, $paketCategoryId]));
            $product->categories()->sync($ids);
            $this->setStock($product, 5);
        }

        $this->command?->info('Paket '.($product->wasRecentlyCreated ? 'ditambahkan' : 'sudah ada, dilewati').': '.$product->slug.' (Rp '.number_format($price, 0, ',', '.').').');
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
