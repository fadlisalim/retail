<?php

namespace Database\Seeders;

use App\Enums\ProductCondition;
use App\Enums\StockMovementType;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use App\Services\StockService;
use App\Services\WatermarkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clearance bekas proyek (Okt 2026): Panel Surya Seraphim SRP-345-6MA-DG
 * 345Wp mono double glass, pernah terpasang (kotoran minor, fungsi normal) —
 * jual Rp 850.000 (modal Rp 450.000), garansi toko 3 tahun, stok 100.
 * Data teknis dari datasheet SRP-6MA-DG (assets/seraphim).
 * Idempotent: produk yang sudah ada tidak ditimpa (harga/stok editan admin aman).
 */
class SeraphimClearanceSeeder extends Seeder
{
    public const SKU = 'SERAPHIM-SRP-345-6MA-DG';

    public const PRICE = 850000;

    public const COST = 450000;

    /** Stok sisa proyek: 100 panel (versi awal seeder memakai placeholder 10 → dinaikkan otomatis). */
    public const INITIAL_STOCK = 100;

    private const OLD_PLACEHOLDER_STOCK = 10;

    private const ASSETS = __DIR__.'/assets/seraphim';

    public function run(): void
    {
        $brand = Brand::firstOrCreate(['slug' => 'seraphim'], [
            'name' => 'Seraphim', 'is_active' => true, 'is_featured' => false, 'sort_order' => 70,
            'description' => 'Seraphim Solar System — produsen panel surya Tier-1 (Bloomberg NEF) sejak 2011, terpasang di 100+ negara.',
            'meta_title' => 'Produk Seraphim — Panel Surya Tier-1',
        ]);
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first() ?? Category::where('slug', 'panel-surya')->first();
        $parent = Category::where('slug', 'panel-surya')->first();

        $existing = Product::where('sku', self::SKU)->first();
        $product = $existing ?? Product::create([
            'sku' => self::SKU,
            'slug' => 'panel-surya-seraphim-345wp-mono-double-glass-sisa-proyek',
            'category_id' => $category?->id,
            'brand_id' => $brand->id,
            'model' => 'SRP-345-6MA-DG',
            'product_type' => 'simple',
            ...self::copy(),
            'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>SRP-345-6MA-DG (seri SRP-6MA-DG, 6 inch 72 cells)</td></tr>
<tr><th>Daya maksimum (Pmax, STC)</th><td>345 W (NOCT: 256 W)</td></tr>
<tr><th>Tegangan open circuit (Voc)</th><td>47,30 V</td></tr>
<tr><th>Arus short circuit (Isc)</th><td>9,24 A</td></tr>
<tr><th>Tegangan daya maksimum (Vmp)</th><td>38,70 V</td></tr>
<tr><th>Arus daya maksimum (Imp)</th><td>8,92 A</td></tr>
<tr><th>Efisiensi modul</th><td>17,60 %</td></tr>
<tr><th>Toleransi daya</th><td>0 / +4,99 W</td></tr>
<tr><th>Tegangan sistem maksimum</th><td>1500 V (TÜV)</td></tr>
<tr><th>Fuse seri maksimum</th><td>15 A</td></tr>
<tr><th>Koefisien suhu</th><td>Pmax −0,40 %/°C · Voc −0,32 %/°C · Isc +0,05 %/°C</td></tr>
<tr><th>Sel surya</th><td>Monocrystalline 156,75 × 156,75 mm, 72 sel</td></tr>
<tr><th>Kaca depan / belakang</th><td>2,0 mm AR tempered low iron / 2,0 mm tempered low iron (double glass, frameless)</td></tr>
<tr><th>Junction box</th><td>IP67</td></tr>
<tr><th>Kabel / konektor</th><td>4,0 mm², 255 mm (+) / 355 mm (−), MC4 compatible</td></tr>
<tr><th>Beban mekanis</th><td>2400 Pa</td></tr>
<tr><th>Dimensi / berat</th><td>1980 × 990 × 5,5 mm / 23 kg</td></tr>
<tr><th>Sertifikasi</th><td>TÜV, CE, CQC · ISO 9001, ISO 14001, OHSAS 18001</td></tr>
<tr><th>Kondisi</th><td>Bekas proyek (pernah terpasang), kotoran/bekas pemakaian minor, fungsi normal</td></tr>
<tr><th>Garansi</th><td>Garansi toko 3 tahun</td></tr>
</tbody></table>
HTML,
            'price' => self::PRICE,
            'sale_price' => null,
            'cost_price' => self::COST,
            'price_status' => 'fixed',
            'price_includes_tax' => false,
            'is_taxable' => true,
            'unit' => 'pcs',
            'min_stock' => 2,
            'weight_grams' => 23000,
            'length_cm' => 200, 'width_cm' => 101, 'height_cm' => 3, // packing kayu
            'package_count' => 1,
            'requires_freight' => true,
            'pickup_only' => false,
            'warranty' => 'Garansi toko 3 tahun',
            'estimated_processing' => '2-5 hari kerja',
            'status' => 'published',
            'is_featured' => true,
            'is_new' => false,
            'is_promo' => false,
            'is_clearance' => true,
            'is_purchasable' => true,
            'requires_quotation' => false,
            'min_purchase' => 1,
            'published_at' => now(),
        ]);

        // Versi awal seeder salah menyebut "Baru - Sisa Proyek": panel ini bekas terpasang di proyek.
        // Perbaiki kondisi + teksnya (harga, stok, foto, dokumen tidak disentuh).
        if ($existing && $existing->condition === ProductCondition::NewProjectSurplus->value) {
            $existing->forceFill(self::copy())->save();
            $existing->conditionDetail()->updateOrCreate([], self::conditionDetail((int) ($existing->conditionDetail?->available_quantity ?? self::INITIAL_STOCK)));
            $this->command?->info('Kondisi diperbaiki: Bekas Pakai (eks proyek, kotoran minor) — teks & detail kondisi diperbarui.');
        }

        // Versi awal seeder mengisi stok placeholder 10; naikkan ke 100 selama belum ada mutasi lain (penjualan/koreksi admin).
        if ($existing) {
            $current = (int) WarehouseStock::where('product_id', $existing->id)->whereNull('product_variant_id')->sum('quantity_available');
            $onlySeeded = StockMovement::where('product_id', $existing->id)->count() === 1;
            if ($current === self::OLD_PLACEHOLDER_STOCK && $onlySeeded) {
                app(StockService::class)->adjust($existing, null, self::INITIAL_STOCK - $current, StockMovementType::Purchase, note: 'Koreksi stok sisa proyek: 100 panel (seeder)');
                $existing->conditionDetail?->update(['available_quantity' => self::INITIAL_STOCK]);
                $this->command?->info('Stok dinaikkan dari placeholder 10 menjadi 100.');
            }
        }

        if (! $existing) {
            $product->categories()->sync(array_values(array_filter([$product->category_id, $parent?->id])));
            $product->conditionDetail()->create(self::conditionDetail(self::INITIAL_STOCK));
            $this->attachAttributes($product);

            $current = (int) WarehouseStock::where('product_id', $product->id)->whereNull('product_variant_id')->sum('quantity_available');
            if ($current < self::INITIAL_STOCK) {
                app(StockService::class)->adjust($product, null, self::INITIAL_STOCK - $current, StockMovementType::Purchase, note: 'Stok awal sisa proyek (seeder)');
            }
        }

        $this->syncImages($product);
        $this->attachBrochure($product, 'Datasheet Seraphim SRP-6MA-DG 345–360W (PDF)', 'srp-6ma-dg-datasheet.pdf');

        $this->command?->info('Seraphim 345Wp clearance: produk '.($existing ? 'sudah ada (harga/teks admin tidak diubah)' : 'ditambahkan — Rp 850.000, modal Rp 450.000, stok '.self::INITIAL_STOCK).'.');
    }

    /**
     * Teks produk yang bergantung pada kondisi (bekas proyek, kotoran minor, fungsi normal).
     *
     * @return array<string, string>
     */
    private static function copy(): array
    {
        return [
            'name' => 'Panel Surya Seraphim 345Wp Mono Double Glass SRP-345-6MA-DG (Bekas Proyek)',
            'condition' => ProductCondition::Used->value,
            'badge_text' => 'Jaminan Termurah',
            'short_description' => 'Panel surya Seraphim 345Wp monocrystalline 72 sel, double glass frameless (PID free). Bekas proyek PLTS: pernah terpasang, ada kotoran/bekas pemakaian minor, fungsi normal. Harga clearance Rp 850.000 — JAMINAN TERMURAH. Garansi toko 3 tahun.',
            'description' => <<<'HTML'
<p><strong>Seraphim SRP-345-6MA-DG</strong> — panel surya monocrystalline 345Wp, 72 sel, konstruksi <strong>double glass tanpa bingkai (frameless)</strong>: kaca tempered 2 mm di depan dan belakang, sehingga tahan lembap, garam, amonia, dan bebas PID. Seraphim adalah produsen panel <strong>Tier-1</strong> yang terpasang di lebih dari 100 negara.</p>
<p><strong>Kondisi: bekas proyek PLTS (pernah terpasang).</strong> Panel dibongkar dari proyek dalam keadaan berfungsi normal. Ada <strong>kotoran / bekas pemakaian minor</strong> di permukaan kaca dan sisi belakang yang bisa dibersihkan, tanpa retak atau cacat fungsi. Dijual <strong>clearance Rp 850.000/panel — JAMINAN TERMURAH</strong>. Garansi toko 3 tahun.</p>
<ul>
<li>⚡ Daya 345Wp (toleransi 0 / +4,99 W), efisiensi modul 17,6% — performa terukur masih &gt;90% dari daya nominal</li>
<li>🔋 Voc 47,3 V · Vmp 38,7 V · Imp 8,92 A — cocok untuk sistem on-grid, hybrid, maupun off-grid 24/48 V dengan MPPT</li>
<li>🧱 Double glass 2 mm + 2 mm, frameless, junction box IP67, konektor MC4 compatible, beban mekanis 2400 Pa</li>
<li>📐 1980 × 990 × 5,5 mm, 23 kg</li>
<li>🏷️ Sertifikasi TÜV, CE, CQC; asuransi produk PICC &amp; Chubb</li>
<li>🚚 Pengiriman kargo dengan packing kayu (ongkir dikonfirmasi admin); ambil di gudang Bandung juga bisa</li>
</ul>
<p><em>Setiap panel dicek sebelum dikirim. Pembelian jumlah banyak (≥10 panel) bisa nego — hubungi kami.</em></p>
HTML,
            'keywords' => 'panel surya 345wp, seraphim, srp-345-6ma-dg, panel surya mono 72 cell, double glass, frameless, bekas proyek, panel surya bekas, clearance panel surya, panel surya murah, solar panel 345w, panel surya 350wp',
            'meta_title' => 'Panel Surya Seraphim 345Wp Mono Double Glass — Bekas Proyek Rp 850.000, Jaminan Termurah',
            'meta_description' => 'Panel surya Seraphim SRP-345-6MA-DG 345Wp mono 72 sel double glass, bekas proyek (kotoran minor, fungsi normal). Clearance Rp 850.000/panel JAMINAN TERMURAH, garansi toko 3 tahun.',
        ];
    }

    /** @return array<string, mixed> */
    private static function conditionDetail(int $qty): array
    {
        return [
            'reason_for_sale' => 'Bongkaran proyek PLTS — panel pernah terpasang dan berfungsi normal, dijual sebagai clearance.',
            'item_location' => 'Gudang Bandung',
            'available_quantity' => $qty,
            'purchase_year' => (int) now()->format('Y') - 1,
            'remaining_warranty' => 'Garansi toko 3 tahun',
            'completeness' => 'Panel + kabel & konektor MC4 bawaan (tanpa dus)',
            'defect_notes' => 'Kotoran / bekas pemakaian minor di kaca dan sisi belakang (bisa dibersihkan). Tidak retak, tidak ada cacat fungsi.',
            'is_returnable' => true,
            'is_negotiable' => true,
            'pickup_required' => false,
            'auto_shipping' => true,
        ];
    }

    /** Atribut terfilter (Spesifikasi Panel Surya) bila AttributeSeeder sudah dijalankan. */
    private function attachAttributes(Product $product): void
    {
        $values = ['Daya Maksimum' => 345, 'Efisiensi Modul' => 17.6, 'Tegangan Open Circuit' => 47.3, 'Arus Short Circuit' => 9.24, 'Jenis Sel' => 'Monocrystalline double glass', 'Garansi Produk' => 3];
        foreach ($values as $name => $value) {
            $id = Attribute::where('slug', Str::slug('Spesifikasi Panel Surya-'.$name))->value('id');
            if ($id) {
                $product->attributeValues()->updateOrCreate(['attribute_id' => $id], is_numeric($value) ? ['value_number' => $value, 'value_text' => null] : ['value_text' => $value, 'value_number' => null]);
            }
        }
    }

    /**
     * Galeri (urut): gambar promo berlogo → foto utama (hanya dioptimalkan, tanpa watermark),
     * foto palet stok (watermark), potongan foto panel dari datasheet (watermark). Idempotent.
     */
    private function syncImages(Product $product): void
    {
        $gallery = [
            ['promo-srp-345-6ma-dg-v2.jpg', true, 'Promo Panel Surya Seraphim 345Wp bekas proyek'], // v2: badge "345 Wp" (bukan "Seri 345–360 Wp")
            ['foto-palet-srp-345.jpg', false, 'Stok panel Seraphim 345Wp bekas proyek di atas palet'],
            ['srp-345-6ma-dg.jpg', false, 'Panel Seraphim SRP-345-6MA-DG (gambar datasheet)'],
        ];
        $watermark = app(WatermarkService::class);
        $paths = [];
        foreach ($gallery as [$file, $branded, $alt]) {
            $src = self::ASSETS.'/'.$file;
            if (! is_file($src)) {
                continue;
            }
            $stem = 'products/seraphim/'.pathinfo($file, PATHINFO_FILENAME);
            $disk = Storage::disk('public');
            if ($disk->exists("{$stem}.webp")) {
                $final = "{$stem}.webp";
            } else {
                if (! $disk->exists("{$stem}.jpg")) {
                    $disk->put("{$stem}.jpg", (string) file_get_contents($src));
                }
                $final = "{$stem}.jpg";
                if ($watermark->isSupported()) {
                    $final = ($branded ? $watermark->optimize($final) : $watermark->apply($final)) ?? $final;
                }
            }
            if (! $product->images()->where('path', $final)->exists()) {
                $product->images()->create(['path' => $final, 'alt' => $alt, 'sort_order' => count($paths), 'watermarked_at' => $watermark->isSupported() ? now() : null]);
            }
            $paths[] = $final;
        }
        if (! $paths) {
            return;
        }
        // Versi lama gambar promo (badge "Seri 345–360 Wp") dibuang dari galeri & disk.
        foreach ($product->images()->where('path', 'like', 'products/seraphim/promo-srp-345-6ma-dg.%')->get() as $old) {
            Storage::disk('public')->delete($old->path);
            $old->delete();
        }
        foreach ($paths as $i => $path) {
            $product->images()->where('path', $path)->update(['sort_order' => $i]);
        }
        if ($product->main_image_path !== $paths[0]) {
            $product->forceFill(['main_image_path' => $paths[0]])->save();
        }
    }

    private function attachBrochure(Product $product, string $title, string $file): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src) || $product->documents()->where('title', $title)->exists()) {
            return;
        }
        $stored = 'products/docs/seraphim-'.$file;
        if (! Storage::disk('public')->exists($stored)) {
            Storage::disk('public')->put($stored, (string) file_get_contents($src));
        }
        $product->documents()->create(['type' => 'brosur', 'title' => $title, 'path' => $stored]);
    }
}
