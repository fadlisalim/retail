<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use App\Services\WatermarkService;
use App\Support\SeedGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PJU Tenaga Surya Aurora — daftar harga EPC Partner PT Aurora Power Energy Global
 * (Agustus 2026), diberikan langsung oleh pemilik, + katalog Optimus LS & datasheet LS-D.
 *
 * Harga modal = harga EPC × 1,11 (supplier memungut PPN → modal termasuk PPN).
 * Harga jual = modal ÷ 0,78 (profit 22% dari harga jual), bulat ke ATAS kelipatan
 * 50.000 — tetap TANPA PPN (PPN ditambahkan saat checkout).
 *
 * Optimus LS & LS-D tayang (foto + spesifikasi dari katalog/datasheet). Copper,
 * Solar X & Fancy belum ada foto/datasheet → dibuat sebagai DRAF; terbitkan lewat
 * Admin setelah foto diunggah. Berat/dimensi bertanda "estimasi" bila tidak ada di
 * dokumen. Stok awal 5/varian = placeholder. Datasheet LS-D dilampirkan versi bersih
 * (kontak distributor diganti kontak Energi.Click).
 *
 * Dijalankan ulang aman (SeedGuard): suntingan/penghapusan admin tidak tertimpa.
 */
class AuroraPjuSeeder extends Seeder
{
    private const ASSETS = __DIR__.'/assets/aurora-pju';

    /** Modal termasuk PPN 11% dari harga EPC. */
    public static function cost(int $epc): int
    {
        return (int) round($epc * 1.11);
    }

    /** Profit 22% dari harga jual → modal ÷ 0,78, bulat ke atas kelipatan 50.000. */
    public static function sellingPrice(int $cost): int
    {
        return (int) (ceil($cost / 0.78 / 50000) * 50000);
    }

    private int $created = 0;

    public function run(): void
    {
        $brand = Brand::firstOrCreate(['slug' => 'aurora'], ['name' => 'Aurora', 'is_active' => true]);
        $pju = Category::where('slug', 'pju-tenaga-surya')->first();
        $categories = [
            'aio' => Category::where('slug', 'pju-tenaga-surya-pju-all-in-one')->first() ?? $pju,
            'tio' => Category::where('slug', 'pju-tenaga-surya-pju-two-in-one')->first() ?? $pju,
        ];

        foreach ($this->products() as $row) {
            $this->seedProduct($brand, array_values(array_filter([$categories[$row['category']]?->id, $pju?->id])), $row);
        }

        $this->command?->info("PJU Aurora: {$this->created} produk baru. Copper, Solar X & Fancy = DRAF (unggah foto lalu terbitkan).");
    }

    private function seedProduct(Brand $brand, array $categoryIds, array $row): void
    {
        $guard = new SeedGuard('aurora-pju:product:'.$row['sku']);
        $product = Product::withTrashed()->where('sku', $row['sku'])->orWhere('slug', $row['slug'])->first();
        if ($product?->trashed() || (! $product && $guard->exists())) {
            return; // dihapus admin
        }

        $variants = $row['variants'];
        $first = $variants[0];
        $fields = [
            'name' => $row['name'], 'model' => $row['model'], 'short_description' => $row['short'],
            'description' => $row['description'], 'specifications' => $row['specifications'],
            'price' => self::sellingPrice(self::cost($first['epc'])), 'cost_price' => self::cost($first['epc']),
            'weight_grams' => $first['weight'],
            'length_cm' => $first['dims'][0], 'width_cm' => $first['dims'][1], 'height_cm' => $first['dims'][2],
            'warranty' => $row['warranty'], 'keywords' => $row['keywords'],
        ];

        if (! $product) {
            $product = Product::create($fields + [
                'sku' => $row['sku'], 'slug' => $row['slug'],
                'category_id' => $categoryIds[0] ?? null, 'brand_id' => $brand->id,
                'product_type' => 'variable', 'condition' => 'new', 'sale_price' => null, 'unit' => 'unit',
                'package_count' => $row['category'] === 'tio' ? 2 : 1, // lampu + panel surya
                'requires_freight' => false, 'is_new' => true, 'is_featured' => false,
                'status' => $row['publish'] ? 'published' : 'draft', 'published_at' => $row['publish'] ? now() : null,
                'estimated_processing' => '3-7 hari kerja',
                'meta_title' => 'PJU Tenaga Surya Aurora '.$row['model'].' — '.brand(),
                'meta_description' => Str::limit($row['short'], 155),
            ]);
            $product->categories()->sync($categoryIds);
            $this->created++;
        }
        $guard->apply($product, $fields);
        $product->save();

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::where('sku', $v['sku'])->first();
            $variantGuard = new SeedGuard('aurora-pju:variant:'.$v['sku']);
            if (! $variant && $variantGuard->exists()) {
                continue; // varian dihapus admin
            }
            $variantFields = [
                'name' => $v['name'], 'option_values' => [$row['option'] => $v['name']],
                'price' => self::sellingPrice(self::cost($v['epc'])), 'cost_price' => self::cost($v['epc']),
                'weight_grams' => $v['weight'],
                'length_cm' => $v['dims'][0], 'width_cm' => $v['dims'][1], 'height_cm' => $v['dims'][2],
            ];
            if (! $variant) {
                $variant = ProductVariant::create($variantFields + [
                    'product_id' => $product->id, 'sku' => $v['sku'], 'sale_price' => null, 'is_active' => true, 'sort_order' => $i,
                ]);
                $this->setStock($product, $variant, 5);
            }
            $variantGuard->apply($variant, $variantFields);
            $variant->save();
            $variantGuard->commit();
        }

        foreach ($row['images'] ?? [] as $i => [$file, $alt, $variantSku]) {
            $this->attachImage($product, $guard, $file, $alt, $i, $variantSku);
        }
        foreach ($row['docs'] ?? [] as [$title, $file]) {
            $this->attachDocument($product, $guard, $title, $file);
        }
        $guard->commit();
    }

    private function attachImage(Product $product, SeedGuard $guard, string $file, string $alt, int $order, ?string $variantSku): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src)) {
            return;
        }
        $disk = Storage::disk('public');
        $stem = 'products/aurora-pju/'.pathinfo($file, PATHINFO_FILENAME).'-'.substr((string) md5_file($src), 0, 8);
        $final = $guard->recorded('path:'.$file);
        if (! $final || ! $disk->exists($final)) {
            $disk->put("{$stem}.jpg", (string) file_get_contents($src));
            $watermark = app(WatermarkService::class);
            $final = $watermark->isSupported() ? ($watermark->apply("{$stem}.jpg") ?? "{$stem}.jpg") : "{$stem}.jpg";
        }

        if ($guard->shouldAdd('images', $file, $product->images()->where('path', $final)->exists())) {
            $product->images()->create(['path' => $final, 'alt' => $alt, 'sort_order' => $order, 'watermarked_at' => now()]);
        }
        if (! $product->images()->where('path', $final)->exists()) {
            return; // dihapus admin
        }
        $guard->remember('path:'.$file, $final);
        if ($order === 0 && $guard->apply($product, ['main_image_path' => $final])) {
            $product->save();
        }
        if ($variantSku) {
            ProductVariant::where('sku', $variantSku)->whereNull('image_path')->update(['image_path' => $final]);
        }
    }

    private function attachDocument(Product $product, SeedGuard $guard, string $title, string $file): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src) || ! $guard->shouldAdd('docs', $title, $product->documents()->where('title', $title)->exists())) {
            return;
        }
        $stored = 'products/docs/aurora-'.$file;
        Storage::disk('public')->put($stored, (string) file_get_contents($src));
        $product->documents()->create(['type' => 'brosur', 'title' => $title, 'path' => $stored]);
    }

    private function setStock(Product $product, ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)->where('product_variant_id', $variant->id)->sum('quantity_available');
        if ($target - $current !== 0) {
            app(StockService::class)->adjust($product, $variant, $target - $current, StockMovementType::Purchase, note: 'Stok awal PJU Aurora (placeholder)');
        }
    }

    /** @return list<array<string, mixed>> */
    private function products(): array
    {
        $soon = '<p><em>Datasheet & foto produk menyusul — hubungi CS untuk spesifikasi lengkap.</em></p>';

        return [
            [
                'sku' => 'AUR-OPTIMUS-LS', 'slug' => 'lampu-jalan-all-in-one-aurora-optimus-ls', 'category' => 'aio', 'publish' => true,
                'name' => 'Lampu Jalan Tenaga Surya All-in-One Aurora Optimus LS 80W – 150W (Garansi 5 Tahun)', 'model' => 'Optimus LS', 'option' => 'Daya',
                'warranty' => 'Garansi 5 tahun',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, aurora, optimus ls, yf-optimus, 80w, 100w, 120w, 150w, motion sensor, lifepo4, mppt',
                'short' => 'PJU all-in-one Aurora Optimus LS: LED 5050 230 lm/W, panel mono 100–140 W, baterai LiFePO4 676–1.150 Wh, MPPT, sensor gerak / time dimming, IP65. Pilihan 80W / 100W / 120W / 150W. Garansi 5 tahun.',
                'description' => '<p><strong>Aurora Optimus LS</strong> — lampu jalan tenaga surya all-in-one kelas proyek untuk jalan kawasan, perumahan, jalan desa, area industri dan parkir. Panel surya monocrystalline, baterai LiFePO4, controller MPPT dan LED efisiensi tinggi menyatu dalam satu bodi — tanpa kabel PLN, tanpa tagihan listrik.</p>'
                    .'<ul><li>💡 LED 5050 efisiensi 230 lm/W, sudut sinar 70°×140°, CRI > 80, 3000–6500K (tipikal 5500–6000K)</li>'
                    .'<li>🔋 Baterai LiFePO4 kapasitas besar (676 Wh – 1.150 Wh) dengan controller MPPT</li>'
                    .'<li>🌙 Mode kerja sensor gerak (motion sensor) / time dimming — terang saat ada aktivitas, hemat saat sepi</li>'
                    .'<li>🛠️ IP65, sertifikat CE & RoHS, suhu kerja −20 hingga +60 °C, tinggi tiang 7–13 m</li></ul>'
                    .'<p>Butuh hitungan jumlah titik & jarak tiang untuk proyek? Konsultasi gratis via WhatsApp.</p>',
                'specifications' => '<table><tbody>'
                    .'<tr><th>Tipe</th><td>80W</td><td>100W / 120W</td><td>150W</td></tr>'
                    .'<tr><th>Panel Surya</th><td>Mono 100 W</td><td>Mono 120 W</td><td>Mono 140 W</td></tr>'
                    .'<tr><th>Baterai LiFePO4</th><td>676 Wh</td><td>896 Wh / 1.030 Wh</td><td>1.150 Wh</td></tr>'
                    .'<tr><th>LED</th><td colspan="3">5050 LED, 230 lm/W, sudut 70°×140°</td></tr>'
                    .'<tr><th>Controller</th><td colspan="3">MPPT</td></tr>'
                    .'<tr><th>Mode Kerja</th><td colspan="3">Sensor gerak / time dimming</td></tr>'
                    .'<tr><th>Suhu Warna</th><td colspan="3">3000–6500K (tipikal 5500–6000K)</td></tr>'
                    .'<tr><th>Proteksi / Sertifikat</th><td colspan="3">IP65 / CE, RoHS</td></tr>'
                    .'<tr><th>Suhu Kerja</th><td colspan="3">−20 – +60 °C</td></tr>'
                    .'<tr><th>Dimensi Produk</th><td>1270×445×73 mm</td><td>1550×445×88 mm</td><td>1730×445×88 mm</td></tr>'
                    .'<tr><th>Ukuran Dus</th><td>1320×520×220 mm</td><td>1600×520×220 mm</td><td>1780×520×220 mm</td></tr>'
                    .'<tr><th>Tinggi Tiang</th><td>7–10 m</td><td>9–12 m</td><td>10–13 m</td></tr>'
                    .'<tr><th>Garansi</th><td colspan="3">5 tahun</td></tr>'
                    .'</tbody></table>',
                // Berat tidak tercantum di katalog → estimasi dari ukuran (setara LS-D).
                'variants' => [
                    ['sku' => 'YF-OPTIMUS-80W-LS', 'name' => '80W', 'epc' => 5_050_000, 'weight' => 18_000, 'dims' => [132, 52, 22]],
                    ['sku' => 'YF-OPTIMUS-100W-LS', 'name' => '100W', 'epc' => 6_225_000, 'weight' => 21_000, 'dims' => [160, 52, 22]],
                    ['sku' => 'YF-OPTIMUS-120W-LS', 'name' => '120W', 'epc' => 6_575_000, 'weight' => 21_000, 'dims' => [160, 52, 22]],
                    ['sku' => 'YF-OPTIMUS-150W-LS', 'name' => '150W', 'epc' => 6_965_000, 'weight' => 24_000, 'dims' => [178, 52, 22]],
                ],
                'images' => [
                    ['optimus-ls-cover.jpg', 'Lampu jalan tenaga surya Aurora Optimus LS', null],
                    ['optimus-ls-fitur.jpg', 'Aurora Optimus LS — LED chip, sensor gerak, bracket', null],
                    ['optimus-ls-lineup.jpg', 'Pilihan daya Aurora Optimus LS', null],
                    ['optimus-ls-malam.jpg', 'Aurora Optimus LS menyala di malam hari', null],
                    ['optimus-ls-spesifikasi.jpg', 'Spesifikasi teknis Aurora Optimus LS', null],
                ],
                'docs' => [['Katalog Aurora Optimus LS (PDF)', 'katalog-optimus-ls.pdf']],
            ],
            [
                'sku' => 'AUR-OPTIMUS-LS-D', 'slug' => 'lampu-jalan-all-in-one-aurora-optimus-ls-d', 'category' => 'aio', 'publish' => true,
                'name' => 'Lampu Jalan Tenaga Surya All-in-One Aurora Optimus LS-D 80W / 100W / 120W', 'model' => 'Optimus LS-D', 'option' => 'Daya',
                'warranty' => 'Garansi 3 tahun',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, aurora, optimus ls-d, yf-opt-m, 80w, 100w, 120w, auto dimming, lifepo4, mppt',
                'short' => 'PJU all-in-one Aurora Optimus LS-D: LED 5050 180 lm/W, panel mono 80–120 W, baterai LiFePO4 287–496 Wh, MPPT dengan modul wireless, auto dimming. Pilihan 80W / 100W / 120W. Garansi 3 tahun.',
                'description' => '<p><strong>Aurora Optimus LS-D</strong> — lampu jalan tenaga surya all-in-one yang hemat biaya untuk jalan lingkungan, perumahan dan desa. Panel mono, baterai LiFePO4 dan controller MPPT dalam satu unit, dengan auto dimming dan smart control.</p>'
                    .'<ul><li>💡 LED SMD 5050, 180 lm/W, distribusi cahaya Type II, sudut 70°×140°, CRI > 80</li>'
                    .'<li>🔋 Baterai LiFePO4 287–496 Wh (umur 8–10 tahun), pengisian 6–8 jam matahari</li>'
                    .'<li>📶 Controller MPPT dengan modul komunikasi wireless — auto dimming & smart control</li>'
                    .'<li>🛡️ Proteksi overcharge, overdischarge, hubung singkat, open load & petir; CE, RoHS, IEC</li></ul>',
                'specifications' => '<table><tbody>'
                    .'<tr><th>Model</th><td>YF-OPT-M-80W-D</td><td>YF-OPT-M-100W-D</td><td>YF-OPT-M-120W-D</td></tr>'
                    .'<tr><th>Panel Surya</th><td>Mono 80 W</td><td>Mono 100 W</td><td>Mono 120 W</td></tr>'
                    .'<tr><th>LED</th><td>54 pcs SMD 5050</td><td>72 pcs SMD 5050</td><td>90 pcs SMD 5050</td></tr>'
                    .'<tr><th>Baterai LiFePO4</th><td>287 Wh</td><td>376 Wh</td><td>496 Wh</td></tr>'
                    .'<tr><th>Efisiensi / Distribusi</th><td colspan="3">180 lm/W · Type II · 70°×140° · CRI > 80</td></tr>'
                    .'<tr><th>Suhu Warna</th><td colspan="3">3000–6500K (tipikal 5500–6000K)</td></tr>'
                    .'<tr><th>Controller</th><td colspan="3">MPPT + modul komunikasi wireless · auto dimming, smart control</td></tr>'
                    .'<tr><th>Pengisian</th><td colspan="3">6–8 jam (STC)</td></tr>'
                    .'<tr><th>Umur</th><td colspan="3">Panel 25 tahun · baterai 8–10 tahun</td></tr>'
                    .'<tr><th>Suhu Kerja</th><td colspan="3">−20 – +60 °C</td></tr>'
                    .'<tr><th>Dimensi Produk</th><td>1140×445×88 mm</td><td>1270×445×88 mm</td><td>1550×445×88 mm</td></tr>'
                    .'<tr><th>Berat Bersih</th><td>14 kg</td><td>16,4 kg</td><td>19 kg</td></tr>'
                    .'<tr><th>Tinggi Tiang</th><td>5–6 m</td><td>6–8 m</td><td>7–10 m</td></tr>'
                    .'<tr><th>Jarak Antar Tiang</th><td colspan="3">20–25 m · diameter bracket ≤ 80 mm</td></tr>'
                    .'<tr><th>Sertifikat</th><td colspan="3">CE, RoHS, IEC</td></tr>'
                    .'<tr><th>Garansi</th><td colspan="3">3 tahun</td></tr>'
                    .'</tbody></table>',
                // Berat = berat kotor per unit (dus isi 2 ÷ 2), dimensi = ukuran dus datasheet.
                'variants' => [
                    ['sku' => 'YF-OPTIMUS-80W-LS-D', 'name' => '80W', 'epc' => 2_885_000, 'weight' => 15_100, 'dims' => [119, 52, 22]],
                    ['sku' => 'YF-OPTIMUS-100W-LS-D', 'name' => '100W', 'epc' => 3_160_000, 'weight' => 17_550, 'dims' => [132, 52, 22]],
                    ['sku' => 'YF-OPTIMUS-120W-LS-D', 'name' => '120W', 'epc' => 3_575_000, 'weight' => 20_300, 'dims' => [160, 49, 19]],
                ],
                'images' => [
                    ['optimus-ls-d-100w.jpg', 'Aurora Optimus LS-D 100W', 'YF-OPTIMUS-100W-LS-D'],
                    ['optimus-ls-d-80w.jpg', 'Aurora Optimus LS-D 80W', 'YF-OPTIMUS-80W-LS-D'],
                    ['optimus-ls-d-120w.jpg', 'Aurora Optimus LS-D 120W', 'YF-OPTIMUS-120W-LS-D'],
                ],
                'docs' => [['Datasheet Aurora Optimus LS-D 80W / 100W / 120W (PDF)', 'datasheet-optimus-ls-d.pdf']],
            ],
            // Tanpa datasheet/foto → DRAF. Berat & ukuran dus = estimasi.
            [
                'sku' => 'AUR-COPPER', 'slug' => 'lampu-jalan-all-in-one-aurora-copper-series', 'category' => 'aio', 'publish' => false,
                'name' => 'Lampu Jalan Tenaga Surya All-in-One Aurora Copper Series 40W / 60W', 'model' => 'Copper Series', 'option' => 'Daya',
                'warranty' => 'Garansi resmi Aurora',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, aurora, copper series, yf-c256, yf-c384, 40w, 60w',
                'short' => 'PJU all-in-one Aurora Copper Series: LED 256 (40W) / LED 384 (60W). Untuk jalan lingkungan, gang dan halaman.',
                'description' => '<p><strong>Aurora Copper Series</strong> — lampu jalan tenaga surya all-in-one ringkas untuk jalan lingkungan, gang, halaman dan area parkir.</p>'.$soon,
                'specifications' => '<table><tbody><tr><th>Tipe</th><td>YF-C256</td><td>YF-C384</td></tr><tr><th>Daya / LED</th><td>40 W · 256 LED</td><td>60 W · 384 LED</td></tr><tr><th>Spesifikasi lengkap</th><td colspan="2">Menyusul</td></tr></tbody></table>',
                'variants' => [
                    ['sku' => 'YF-C256', 'name' => '40W (LED 256)', 'epc' => 1_925_000, 'weight' => 8_000, 'dims' => [80, 35, 15]],
                    ['sku' => 'YF-C384', 'name' => '60W (LED 384)', 'epc' => 2_450_000, 'weight' => 10_000, 'dims' => [95, 35, 15]],
                ],
            ],
            [
                'sku' => 'AUR-SOLAR-X', 'slug' => 'lampu-jalan-two-in-one-aurora-solar-x', 'category' => 'tio', 'publish' => false,
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One Aurora Solar X 60W / 80W / 100W', 'model' => 'Solar X', 'option' => 'Daya',
                'warranty' => 'Garansi resmi Aurora',
                'keywords' => 'pju tenaga surya, lampu jalan two in one, aurora, solar x, yf-slx, 60w, 80w, 100w',
                'short' => 'PJU two-in-one Aurora Solar X 60W / 80W / 100W: lampu + panel surya terpisah. Harga belum termasuk bracket panel surya.',
                'description' => '<p><strong>Aurora Solar X</strong> — lampu jalan tenaga surya two-in-one (lampu dan panel surya terpisah) untuk jalan utama kawasan dan proyek.</p><p><strong>Catatan: harga belum termasuk bracket panel surya.</strong></p>'.$soon,
                'specifications' => '<table><tbody><tr><th>Tipe</th><td>YF-SLX-60W</td><td>YF-SLX-80W</td><td>YF-SLX-100W</td></tr><tr><th>Daya</th><td>60 W</td><td>80 W</td><td>100 W</td></tr><tr><th>Catatan</th><td colspan="3">Belum termasuk bracket panel surya</td></tr><tr><th>Spesifikasi lengkap</th><td colspan="3">Menyusul</td></tr></tbody></table>',
                'variants' => [
                    ['sku' => 'YF-SLX-60W', 'name' => '60W', 'epc' => 6_555_000, 'weight' => 20_000, 'dims' => [120, 60, 20]],
                    ['sku' => 'YF-SLX-80W', 'name' => '80W', 'epc' => 7_725_000, 'weight' => 24_000, 'dims' => [130, 65, 20]],
                    ['sku' => 'YF-SLX-100W', 'name' => '100W', 'epc' => 9_915_000, 'weight' => 28_000, 'dims' => [150, 70, 20]],
                ],
            ],
            [
                'sku' => 'AUR-FANCY', 'slug' => 'lampu-jalan-two-in-one-aurora-fancy-series', 'category' => 'tio', 'publish' => false,
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One Aurora Fancy Series 40W – 100W', 'model' => 'Fancy Series', 'option' => 'Daya',
                'warranty' => 'Garansi resmi Aurora',
                'keywords' => 'pju tenaga surya, lampu jalan two in one, lampu taman, aurora, fancy series, yf-f40w, yf-f60w, yf-f80w, yf-f100w',
                'short' => 'PJU two-in-one Aurora Fancy Series 40W / 60W / 80W / 100W: lampu + panel surya terpisah. Harga belum termasuk bracket panel surya.',
                'description' => '<p><strong>Aurora Fancy Series</strong> — lampu jalan tenaga surya two-in-one bergaya dekoratif untuk perumahan, taman, kawasan wisata dan area komersial.</p><p><strong>Catatan: harga belum termasuk bracket panel surya.</strong></p>'.$soon,
                'specifications' => '<table><tbody><tr><th>Tipe</th><td>YF-F40W</td><td>YF-F60W</td><td>YF-F80W</td><td>YF-F100W</td></tr><tr><th>Daya</th><td>40 W</td><td>60 W</td><td>80 W</td><td>100 W</td></tr><tr><th>Catatan</th><td colspan="4">Belum termasuk bracket panel surya</td></tr><tr><th>Spesifikasi lengkap</th><td colspan="4">Menyusul</td></tr></tbody></table>',
                'variants' => [
                    ['sku' => 'YF-F40W', 'name' => '40W', 'epc' => 3_575_000, 'weight' => 14_000, 'dims' => [100, 55, 20]],
                    ['sku' => 'YF-F60W', 'name' => '60W', 'epc' => 3_825_000, 'weight' => 16_000, 'dims' => [110, 60, 20]],
                    ['sku' => 'YF-F80W', 'name' => '80W', 'epc' => 4_335_000, 'weight' => 18_000, 'dims' => [120, 65, 20]],
                    ['sku' => 'YF-F100W', 'name' => '100W', 'epc' => 4_535_000, 'weight' => 20_000, 'dims' => [130, 70, 20]],
                ],
            ],
        ];
    }
}
