<?php

namespace Database\Seeders;

use App\Enums\ProductCondition;
use App\Enums\StockMovementType;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use App\Services\WatermarkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Clearance sisa proyek (Okt 2026): Panel Surya Seraphim SRP-345-6MA-DG
 * 345Wp mono double glass — jual Rp 850.000 (modal Rp 450.000), garansi
 * toko 3 tahun. Data teknis dari datasheet SRP-6MA-DG (assets/seraphim).
 * Idempotent: produk yang sudah ada tidak ditimpa (harga/stok editan admin aman).
 */
class SeraphimClearanceSeeder extends Seeder
{
    public const SKU = 'SERAPHIM-SRP-345-6MA-DG';

    public const PRICE = 850000;

    public const COST = 450000;

    /** Stok awal placeholder — sesuaikan jumlah sisa proyek sebenarnya lewat Edit Cepat Produk. */
    public const INITIAL_STOCK = 10;

    private const ASSETS = __DIR__.'/assets/seraphim';

    private const SHORT = 'Panel surya Seraphim 345Wp monocrystalline 72 sel, double glass frameless (PID free), kondisi BARU sisa proyek. Harga clearance Rp 850.000 — JAMINAN TERMURAH. Garansi toko 3 tahun.';

    /** Kalimat pembanding harga marketplace dari versi awal seeder → diganti "JAMINAN TERMURAH". */
    private const OLD_COMPARISON = ' — bandingkan dengan panel 350Wp baru di marketplace yang umumnya Rp 1,6–1,9 juta.';

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
            'name' => 'Panel Surya Seraphim 345Wp Mono Double Glass SRP-345-6MA-DG (Sisa Proyek)',
            'category_id' => $category?->id,
            'brand_id' => $brand->id,
            'model' => 'SRP-345-6MA-DG',
            'product_type' => 'simple',
            'condition' => ProductCondition::NewProjectSurplus->value,
            'short_description' => self::SHORT,
            'description' => <<<'HTML'
<p><strong>Seraphim SRP-345-6MA-DG</strong> — panel surya monocrystalline 345Wp, 72 sel, konstruksi <strong>double glass tanpa bingkai (frameless)</strong>: kaca tempered 2 mm di depan dan belakang, sehingga tahan lembap, garam, amonia, dan bebas PID. Seraphim adalah produsen panel <strong>Tier-1</strong> yang terpasang di lebih dari 100 negara.</p>
<p><strong>Kondisi: BARU, sisa proyek PLTS.</strong> Unit belum pernah dipasang, masih dalam kemasan. Karena stok kelebihan proyek, dijual <strong>clearance Rp 850.000/panel — JAMINAN TERMURAH</strong>. Garansi toko 3 tahun.</p>
<ul>
<li>⚡ Daya 345Wp (toleransi 0 / +4,99 W), efisiensi modul 17,6%</li>
<li>🔋 Voc 47,3 V · Vmp 38,7 V · Imp 8,92 A — cocok untuk sistem on-grid, hybrid, maupun off-grid 24/48 V dengan MPPT</li>
<li>🧱 Double glass 2 mm + 2 mm, frameless, junction box IP67, konektor MC4 compatible, beban mekanis 2400 Pa</li>
<li>📐 1980 × 990 × 5,5 mm, 23 kg</li>
<li>🏷️ Sertifikasi TÜV, CE, CQC; asuransi produk PICC &amp; Chubb</li>
<li>🚚 Pengiriman kargo dengan packing kayu (ongkir dikonfirmasi admin); ambil di gudang Bandung juga bisa</li>
</ul>
<p><em>Stok terbatas sesuai sisa proyek. Pembelian jumlah banyak (≥10 panel) bisa nego — hubungi kami.</em></p>
HTML,
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
<tr><th>Garansi</th><td>Garansi toko 3 tahun (sisa proyek)</td></tr>
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
            'badge_text' => 'Jaminan Termurah',
            'is_purchasable' => true,
            'requires_quotation' => false,
            'min_purchase' => 1,
            'keywords' => 'panel surya 345wp, seraphim, srp-345-6ma-dg, panel surya mono 72 cell, double glass, frameless, sisa proyek, clearance panel surya, panel surya murah, solar panel 345w, panel surya 350wp',
            'meta_title' => 'Panel Surya Seraphim 345Wp Mono Double Glass — Clearance Sisa Proyek Rp 850.000, Jaminan Termurah',
            'meta_description' => 'Panel surya Seraphim SRP-345-6MA-DG 345Wp mono 72 sel double glass, kondisi baru sisa proyek. Clearance Rp 850.000/panel JAMINAN TERMURAH, garansi toko 3 tahun. Stok terbatas.',
            'published_at' => now(),
        ]);

        if ($existing && str_contains((string) $existing->description, self::OLD_COMPARISON)) {
            $existing->forceFill([
                'description' => str_replace(
                    ['<strong>clearance Rp 850.000/panel</strong>'.self::OLD_COMPARISON, self::OLD_COMPARISON],
                    ['<strong>clearance Rp 850.000/panel — JAMINAN TERMURAH</strong>.', '.'],
                    $existing->description,
                ),
                'short_description' => str_contains((string) $existing->short_description, 'jauh di bawah harga pasar') ? self::SHORT : $existing->short_description,
                'badge_text' => $existing->badge_text === 'Clearance' ? 'Jaminan Termurah' : $existing->badge_text,
            ])->save();
            $this->command?->info('Keterangan pembanding harga marketplace diganti "JAMINAN TERMURAH".');
        }

        if (! $existing) {
            $product->categories()->sync(array_values(array_filter([$product->category_id, $parent?->id])));
            $product->conditionDetail()->create([
                'reason_for_sale' => 'Kelebihan stok proyek PLTS — unit baru, belum pernah dipasang, masih dalam kemasan.',
                'item_location' => 'Gudang Bandung',
                'available_quantity' => self::INITIAL_STOCK,
                'purchase_year' => (int) now()->format('Y'),
                'remaining_warranty' => 'Garansi toko 3 tahun',
                'completeness' => 'Panel + kabel & konektor MC4 bawaan',
                'defect_notes' => 'Tidak ada cacat fungsi. Kemasan luar mungkin ada bekas penyimpanan.',
                'is_returnable' => true,
                'is_negotiable' => true,
                'pickup_required' => false,
                'auto_shipping' => true,
            ]);
            $this->attachAttributes($product);
            $this->attachImage($product, 'srp-345-6ma-dg.jpg');

            $current = (int) WarehouseStock::where('product_id', $product->id)->whereNull('product_variant_id')->sum('quantity_available');
            if ($current < self::INITIAL_STOCK) {
                app(StockService::class)->adjust($product, null, self::INITIAL_STOCK - $current, StockMovementType::Purchase, note: 'Stok awal sisa proyek (seeder)');
            }
        }

        $this->attachBrochure($product, 'Datasheet Seraphim SRP-6MA-DG 345–360W (PDF)', 'srp-6ma-dg-datasheet.pdf');

        $this->command?->info('Seraphim 345Wp clearance: produk '.($existing ? 'sudah ada (tidak diubah)' : 'ditambahkan — Rp 850.000, modal Rp 450.000, stok awal '.self::INITIAL_STOCK).'.');
        if (! $existing) {
            $this->command?->warn('Stok awal '.self::INITIAL_STOCK.' adalah placeholder — sesuaikan jumlah sisa proyek sebenarnya di Edit Cepat Produk.');
        }
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

    private function attachImage(Product $product, string $file): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src)) {
            return;
        }
        $stored = 'products/seraphim/'.pathinfo($file, PATHINFO_FILENAME).'.jpg';
        if (! Storage::disk('public')->exists($stored)) {
            Storage::disk('public')->put($stored, (string) file_get_contents($src));
        }
        $watermark = app(WatermarkService::class);
        $final = $watermark->isSupported() ? ($watermark->apply($stored) ?? $stored) : $stored;
        $product->images()->create(['path' => $final, 'alt' => $product->name, 'sort_order' => 0, 'watermarked_at' => $watermark->isSupported() ? now() : null]);
        $product->forceFill(['main_image_path' => $final])->save();
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
