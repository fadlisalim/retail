<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use App\Services\WatermarkService;
use App\Support\SeedGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Panel Surya Seraphim Seco SRP-540-BMA-HV 540Wp (PERC mono 144 half-cell, 182 mm) — baru,
 * garansi 10 tahun, stok 50 (pemilik, 30 Okt 2026). Harga jual Rp 1.600.000 TANPA PPN,
 * modal Rp 1.050.000 (sudah termasuk PPN) → margin 34%. Data teknis dari datasheet Seco
 * 535–560W (assets/seraphim). Jalan ulang aman (SeedGuard): suntingan/penghapusan admin menang.
 */
class SeraphimSeco540Seeder extends Seeder
{
    public const SKU = 'SERAPHIM-SRP-540-BMA-HV';

    public const PRICE = 1_600_000;

    public const COST = 1_050_000;

    public const INITIAL_STOCK = 50;

    private const ASSETS = __DIR__.'/assets/seraphim';

    public function run(): void
    {
        $guard = new SeedGuard('seraphim-seco:product:'.self::SKU);
        $product = Product::withTrashed()->where('sku', self::SKU)->first();
        if ($product?->trashed() || (! $product && $guard->exists())) {
            $this->command?->info('Seraphim Seco 540Wp dihapus admin — dilewati.');

            return;
        }

        $brand = Brand::firstOrCreate(['slug' => 'seraphim'], [
            'name' => 'Seraphim', 'is_active' => true, 'is_featured' => false, 'sort_order' => 70,
            'description' => 'Seraphim Solar System — produsen panel surya Tier-1 (Bloomberg NEF) sejak 2011, terpasang di 100+ negara.',
        ]);
        $category = Category::where('slug', 'panel-surya-monocrystalline')->first() ?? Category::where('slug', 'panel-surya')->first();
        $parent = Category::where('slug', 'panel-surya')->first();

        $fields = self::fields();
        $created = ! $product;
        if ($created) {
            $product = Product::create($fields + [
                'sku' => self::SKU,
                'slug' => 'panel-surya-seraphim-seco-540wp-mono-perc-srp-540-bma-hv',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'product_type' => 'simple',
                'condition' => 'new',
                'sale_price' => null,
                'price_status' => 'fixed',
                'price_includes_tax' => false,
                'is_taxable' => true,
                'unit' => 'pcs',
                'min_stock' => 5,
                'package_count' => 1,
                'requires_freight' => true,
                'pickup_only' => false,
                'estimated_processing' => '2-5 hari kerja',
                'status' => 'published',
                'is_featured' => true,
                'is_new' => true,
                'is_purchasable' => true,
                'requires_quotation' => false,
                'min_purchase' => 1,
                'published_at' => now(),
            ]);
            $product->categories()->sync(array_values(array_unique(array_filter([$product->category_id, $parent?->id]))));
            $this->attachAttributes($product);
            app(StockService::class)->adjust($product, null, self::INITIAL_STOCK, StockMovementType::Purchase, note: 'Stok awal Seraphim Seco 540Wp (seeder)');
        }
        $guard->apply($product, $fields);
        $product->save();

        $this->attachImage($product, $guard, 'seco-540-bma-hv.jpg', 'Panel surya Seraphim Seco 540Wp tampak depan & belakang');
        $this->attachDocument($product, $guard, 'Datasheet Seraphim Seco 535–560W (PDF)', 'seco-535-560-datasheet.pdf');
        $guard->commit();

        $this->command?->info('Seraphim Seco 540Wp: '.($created ? 'ditambahkan — Rp 1.600.000, modal Rp 1.050.000, stok '.self::INITIAL_STOCK : 'sudah ada (suntingan admin dipertahankan)').'.');
    }

    /** @return array<string, mixed> */
    private static function fields(): array
    {
        return [
            'name' => 'Panel Surya Seraphim Seco 540Wp Mono PERC Half Cell SRP-540-BMA-HV',
            'model' => 'SRP-540-BMA-HV',
            'price' => self::PRICE,
            'cost_price' => self::COST,
            'warranty' => 'Garansi 10 tahun',
            'weight_grams' => 27000,
            'length_cm' => 228, 'width_cm' => 114, 'height_cm' => 4,
            'short_description' => 'Panel surya Seraphim Seco 540Wp mono PERC 144 half-cell (182 mm), efisiensi 20,9%. Baru, garansi 10 tahun. Cocok untuk PLTS rumah, bisnis, maupun proyek — Rp 1,6 jt/panel (± Rp 2.960/Wp).',
            'description' => <<<'HTML'
<p><strong>Seraphim Seco SRP-540-BMA-HV</strong> — panel surya monofacial <strong>540Wp</strong> dengan sel <strong>PERC monocrystalline 182 mm, 144 half-cell</strong>. Seraphim adalah produsen panel <strong>Tier-1</strong> yang terpasang di lebih dari 100 negara, dengan asuransi produk PICC.</p>
<p>Harga <strong>Rp 1.600.000/panel</strong> — sekitar <strong>Rp 2.960 per Wp</strong>, salah satu biaya per watt paling hemat untuk panel baru bergaransi. <strong>Garansi 10 tahun.</strong></p>
<ul>
<li>⚡ Daya 540Wp (toleransi 0 / +4,99 W), efisiensi modul 20,9%</li>
<li>🔋 Voc 49,5 V · Isc 13,81 A · Vmp 41,55 V · Imp 13,00 A — cocok untuk inverter on-grid, hybrid, dan off-grid dengan MPPT tegangan tinggi</li>
<li>🌥️ Half-cell: rugi daya lebih kecil saat sebagian panel tertutup bayangan, performa cahaya rendah sangat baik</li>
<li>🧱 Kaca tempered AR 3,2 mm, frame aluminium anodized, junction box IP68 (3 dioda), beban angin 2400 Pa &amp; salju 5400 Pa</li>
<li>📐 2278 × 1134 × 35 mm, 27 kg</li>
<li>🏷️ Sertifikasi TÜV, CE, CQC, CSA · ISO 9001 / 14001 / 45001 · 3× uji EL</li>
<li>🚚 Pengiriman kargo (ongkir dikonfirmasi admin); ambil di gudang Bandung juga bisa</li>
</ul>
<p><em>Pembelian jumlah banyak (≥10 panel) bisa nego — hubungi kami.</em></p>
HTML,
            'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Model</th><td>SRP-540-BMA-HV (seri Seco 535–560W)</td></tr>
<tr><th>Daya maksimum (Pmax, STC)</th><td>540 W (NOCT: 406 W)</td></tr>
<tr><th>Tegangan open circuit (Voc)</th><td>49,50 V</td></tr>
<tr><th>Arus short circuit (Isc)</th><td>13,81 A</td></tr>
<tr><th>Tegangan daya maksimum (Vmp)</th><td>41,55 V</td></tr>
<tr><th>Arus daya maksimum (Imp)</th><td>13,00 A</td></tr>
<tr><th>Efisiensi modul</th><td>20,90 %</td></tr>
<tr><th>Toleransi daya</th><td>0 / +4,99 W</td></tr>
<tr><th>Tegangan sistem maksimum</th><td>1500 V DC</td></tr>
<tr><th>Fuse seri maksimum</th><td>25 A</td></tr>
<tr><th>Koefisien suhu</th><td>Pmax −0,34 %/°C · Voc −0,26 %/°C · Isc +0,05 %/°C</td></tr>
<tr><th>NOCT</th><td>45 ± 2 °C</td></tr>
<tr><th>Suhu operasi</th><td>−40 s.d. +85 °C</td></tr>
<tr><th>Sel surya</th><td>PERC monocrystalline 182 mm, 144 half-cell (monofacial)</td></tr>
<tr><th>Kaca depan</th><td>3,2 mm AR coating tempered glass, low iron</td></tr>
<tr><th>Frame</th><td>Aluminium alloy anodized</td></tr>
<tr><th>Junction box</th><td>IP68, 3 dioda</td></tr>
<tr><th>Kabel</th><td>4,0 mm², 250 mm (+) / 350 mm (−)</td></tr>
<tr><th>Beban mekanis</th><td>Depan 5400 Pa / belakang 2400 Pa</td></tr>
<tr><th>Dimensi / berat</th><td>2278 × 1134 × 35 mm / 27 kg</td></tr>
<tr><th>Kemasan</th><td>31 panel per palet</td></tr>
<tr><th>Sertifikasi</th><td>TÜV, CE, PV Cycle, CQC, CSA · ISO 9001, ISO 14001, ISO 45001</td></tr>
<tr><th>Kondisi</th><td>Baru</td></tr>
<tr><th>Garansi</th><td>10 tahun</td></tr>
</tbody></table>
HTML,
            'keywords' => 'panel surya 540wp, seraphim, seraphim seco, srp-540-bma-hv, panel surya mono perc, half cell, panel surya 550wp, solar panel 540w, panel surya murah, panel surya tier 1, plts rumah, plts on grid',
            'meta_title' => 'Panel Surya Seraphim Seco 540Wp Mono PERC — Rp 1,6 jt, Garansi 10 Tahun',
            'meta_description' => 'Jual panel surya Seraphim Seco SRP-540-BMA-HV 540Wp mono PERC 144 half-cell, efisiensi 20,9%. Baru, garansi 10 tahun, Rp 1.600.000/panel (± Rp 2.960/Wp). Stok siap kirim.',
        ];
    }

    /** Atribut terfilter (Spesifikasi Panel Surya) bila AttributeSeeder sudah dijalankan. */
    private function attachAttributes(Product $product): void
    {
        $values = ['Daya Maksimum' => 540, 'Efisiensi Modul' => 20.9, 'Tegangan Open Circuit' => 49.5, 'Arus Short Circuit' => 13.81, 'Jenis Sel' => 'Monocrystalline PERC half-cell', 'Garansi Produk' => 10];
        foreach ($values as $name => $value) {
            $id = Attribute::where('slug', Str::slug('Spesifikasi Panel Surya-'.$name))->value('id');
            if ($id) {
                $product->attributeValues()->updateOrCreate(['attribute_id' => $id], is_numeric($value) ? ['value_number' => $value, 'value_text' => null] : ['value_text' => $value, 'value_number' => null]);
            }
        }
    }

    private function attachImage(Product $product, SeedGuard $guard, string $file, string $alt): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src)) {
            return;
        }
        $disk = Storage::disk('public');
        $stem = 'products/seraphim/'.pathinfo($file, PATHINFO_FILENAME).'-'.substr((string) md5_file($src), 0, 8);
        $final = $guard->recorded('path:'.$file);
        if (! $final || ! $disk->exists($final)) {
            $disk->put("{$stem}.jpg", (string) file_get_contents($src));
            $watermark = app(WatermarkService::class);
            $final = $watermark->isSupported() ? ($watermark->apply("{$stem}.jpg", square: true) ?? "{$stem}.jpg") : "{$stem}.jpg";
        }

        if ($guard->shouldAdd('images', $file, $product->images()->where('path', $final)->exists())) {
            $product->images()->create(['path' => $final, 'alt' => $alt, 'sort_order' => 0, 'watermarked_at' => now()]);
        }
        if (! $product->images()->where('path', $final)->exists()) {
            return; // dihapus admin
        }
        $guard->remember('path:'.$file, $final);
        if ($guard->apply($product, ['main_image_path' => $final])) {
            $product->save();
        }
    }

    private function attachDocument(Product $product, SeedGuard $guard, string $title, string $file): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src) || ! $guard->shouldAdd('docs', $title, $product->documents()->where('title', $title)->exists())) {
            return;
        }
        $stored = 'products/docs/seraphim-'.$file;
        Storage::disk('public')->put($stored, (string) file_get_contents($src));
        $product->documents()->create(['type' => 'brosur', 'title' => $title, 'path' => $stored]);
    }
}
