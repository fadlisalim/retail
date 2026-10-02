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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lampu PJU Tenaga Surya (Okt 2026) — 12 produk dari daftar harga + brosur:
 * ICOM (IC-AIOM, AIO sensor/dimming, IC-TEEN, IC-YIN, IC-FIN), SOLARI SL-MW,
 * LEIND (LI-RON, LI-CITY, LI-ZLW Mini, LI-SLIM, LI-VILL), SUNYO SY-BEK.
 * All-in-One = panel+baterai+lampu satu unit; Two-in-One = lampu + panel surya
 * terpisah (dijual sepaket).
 *
 * Angka di daftar harga (Okt 2026) = HARGA MODAL. Harga jual = modal ÷ 0,75
 * (profit 25% dari harga jual), dibulatkan ke ATAS ke kelipatan 10.000.
 * Berat/dimensi dari brosur; yang tidak ada di brosur = estimasi (ditandai di
 * komentar). Idempotent: produk yang sudah ada tidak ditimpa (editan admin
 * aman) — kecuali baris yang masih memakai angka modal sebagai harga jual
 * (versi awal seeder) yang dikoreksi ke modal + harga jual; varian baru
 * dilengkapi; foto dari database/seeders/assets/pju hanya dipasang saat produk
 * pertama dibuat; brosur PDF dari folder yang sama dilampirkan ke tab Dokumen.
 */
class PjuTenagaSuryaSeeder extends Seeder
{
    private const ASSETS = __DIR__.'/assets/pju';

    /** Profit 25% dari harga jual → harga jual = modal ÷ 0,75, bulat ke atas kelipatan 10.000. */
    public static function sellingPrice(int $cost): int
    {
        return (int) (ceil($cost / 0.75 / 10000) * 10000);
    }

    private StockService $stock;

    private WatermarkService $watermark;

    private array $categories = [];

    private array $brands = [];

    private int $created = 0;

    private int $skipped = 0;

    private array $brosurMissing = [];

    private int $repriced = 0;

    public function run(): void
    {
        $this->stock = app(StockService::class);
        $this->watermark = app(WatermarkService::class);

        $pju = Category::where('slug', 'pju-tenaga-surya')->first();
        $this->categories = [
            'aio' => Category::where('slug', 'pju-tenaga-surya-pju-all-in-one')->first() ?? $pju,
            'tio' => Category::where('slug', 'pju-tenaga-surya-pju-two-in-one')->first() ?? $pju,
            'parent' => $pju,
        ];

        foreach (['ICOM', 'Solari', 'LEIND', 'Sunyo'] as $i => $name) {
            $this->brands[Str::slug($name)] = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'is_featured' => false, 'sort_order' => 50 + $i,
                    'description' => "Lampu PJU tenaga surya {$name}.", 'meta_title' => "Produk {$name} — PJU Tenaga Surya"],
            );
        }

        foreach ($this->products() as $row) {
            $this->seedProduct($row);
        }

        $this->command?->info("PJU Tenaga Surya: {$this->created} produk ditambahkan, {$this->skipped} sudah ada (dilewati), {$this->repriced} baris harga dikoreksi (modal → jual ÷ 0,75).");
        if ($this->brosurMissing) {
            $this->command?->warn('Brosur PDF belum terpasang untuk: '.implode(', ', array_unique($this->brosurMissing)));
            $this->command?->line('Taruh file PDF brosur di storage/app/pju-brosur/ (nama sesuai di atas), lalu jalankan seeder ini lagi — atau upload lewat Admin → Produk → Edit → Dokumen.');
        }
        $this->command?->warn('Harga jual = modal ÷ 0,75 (profit 25%), bulat ke atas 10.000. Stok awal 5/varian = placeholder.');
    }

    /** @param array<string, mixed> $row */
    private function seedProduct(array $row): void
    {
        $existing = Product::where('sku', $row['sku'])->orWhere('slug', $row['slug'])->first();
        $variants = $row['variants'] ?? [];
        $first = $variants[0] ?? null;

        $product = $existing ?? Product::create([
            'slug' => $row['slug'],
            'sku' => $row['sku'],
            'name' => $row['name'],
            'category_id' => $this->categories[$row['category']]?->id,
            'brand_id' => $this->brands[$row['brand']]->id,
            'model' => $row['model'],
            'product_type' => $variants ? 'variable' : 'simple',
            'condition' => 'new',
            'short_description' => $row['short'],
            'description' => $row['description'],
            'specifications' => $row['specifications'],
            'price' => self::sellingPrice($first['cost'] ?? $row['cost']),
            'sale_price' => null,
            'cost_price' => $first['cost'] ?? $row['cost'],
            'unit' => 'unit',
            'weight_grams' => $first['weight'] ?? $row['weight'],
            'length_cm' => ($first['dims'] ?? $row['dims'])[0],
            'width_cm' => ($first['dims'] ?? $row['dims'])[1],
            'height_cm' => ($first['dims'] ?? $row['dims'])[2],
            'package_count' => $row['category'] === 'tio' ? 2 : 1, // lampu + panel surya
            'requires_freight' => false,
            'warranty' => $row['warranty'],
            'keywords' => $row['keywords'],
            'is_new' => true,
            'is_featured' => $row['featured'] ?? false,
            'status' => 'published',
            'published_at' => now(),
            'estimated_processing' => '3-7 hari kerja',
            'meta_title' => $row['name'].' — '.$row['meta_tail'],
            'meta_description' => Str::limit($row['short'], 155),
        ]);

        if ($existing) {
            $this->skipped++;
            // Versi awal seeder memakai angka modal sebagai harga jual — koreksi
            // hanya baris yang masih persis seperti itu (editan admin dibiarkan).
            $cost = $first['cost'] ?? $row['cost'];
            if ($product->cost_price === null && (int) $product->price === $cost) {
                $product->forceFill(['cost_price' => $cost, 'price' => self::sellingPrice($cost)])->save();
                $this->repriced++;
            }
        } else {
            $this->created++;
            $product->categories()->sync(array_values(array_filter([$product->category_id, $this->categories['parent']?->id])));
            if (! $variants && ($row['image'] ?? null)) {
                $this->attachImage($product, null, $row['image']);
            }
            if (! $variants) {
                $this->setStock($product, null, 5);
            }
        }

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::firstOrCreate(
                ['sku' => $v['sku']],
                [
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'option_values' => [$row['option'] => $v['name']],
                    'price' => self::sellingPrice($v['cost']),
                    'sale_price' => null,
                    'cost_price' => $v['cost'],
                    'weight_grams' => $v['weight'],
                    'length_cm' => $v['dims'][0], 'width_cm' => $v['dims'][1], 'height_cm' => $v['dims'][2],
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
            if (! $variant->wasRecentlyCreated && $variant->cost_price === null && (int) $variant->price === $v['cost']) {
                $variant->forceFill(['cost_price' => $v['cost'], 'price' => self::sellingPrice($v['cost'])])->save();
                $this->repriced++;
            }
            if ($variant->wasRecentlyCreated) {
                if ($v['image'] ?? $row['image'] ?? null) {
                    $this->attachImage($product, $variant, $v['image'] ?? $row['image']);
                }
                $this->setStock($product, $variant, 5);
            }
        }

        foreach ($row['brosur'] ?? [] as $title => $file) {
            $this->attachBrochure($product, $title, $file);
        }
    }

    /** Salin foto dari assets ke disk public (watermark bila didukung); varian dapat foto sendiri, produk dapat foto utama. */
    private function attachImage(Product $product, ?ProductVariant $variant, string $file): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src)) {
            return;
        }

        $stored = 'products/pju/'.pathinfo($file, PATHINFO_FILENAME).'.jpg';
        if (! Storage::disk('public')->exists($stored)) {
            Storage::disk('public')->put($stored, (string) file_get_contents($src));
        }
        $final = $stored;
        $watermarked = null;
        if ($this->watermark->isSupported()) {
            $final = $this->watermark->apply($stored) ?? $stored;
            $watermarked = now();
        }

        if ($variant) {
            $variant->forceFill(['image_path' => $final])->save();
        }
        if (! $product->images()->where('path', $final)->exists()) {
            $product->images()->create([
                'path' => $final, 'alt' => $product->name.($variant ? ' — '.$variant->name : ''),
                'sort_order' => (int) $product->images()->max('sort_order') + 1, 'watermarked_at' => $watermarked,
            ]);
        }
        if (! $product->main_image_path) {
            $product->forceFill(['main_image_path' => $final])->save();
        }
    }

    /** Brosur PDF: dari storage/app/pju-brosur/ atau assets (tidak di-commit); dilewati bila belum ada. */
    private function attachBrochure(Product $product, string $title, string $file): void
    {
        if ($product->documents()->where('title', $title)->exists()) {
            return;
        }

        $src = collect([self::ASSETS.'/'.$file, storage_path('app/pju-brosur/'.$file)])->first(fn ($p) => is_file($p));
        if (! $src) {
            $this->brosurMissing[] = $file;

            return;
        }

        $stored = 'products/docs/pju-'.$file;
        if (! Storage::disk('public')->exists($stored)) {
            Storage::disk('public')->put($stored, (string) File::get($src));
        }
        $product->documents()->create(['type' => 'brosur', 'title' => $title, 'path' => $stored]);
    }

    private function setStock(Product $product, ?ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)->sum('quantity_available');
        if ($target - $current !== 0) {
            $this->stock->adjust($product, $variant, $target - $current, StockMovementType::Purchase, note: 'Stok awal (seeder)');
        }
    }

    /** @return list<array<string, mixed>> */
    private function products(): array
    {
        $tioNote = '<p><em>Two-in-One: lampu dan panel surya terpisah, dijual sepaket (lampu + panel + kabel). Tiang tidak termasuk. Instalasi dapat dibantu tim kami.</em></p>';
        $aioNote = '<p><em>All-in-One: panel surya, baterai, dan lampu dalam satu unit — tinggal pasang di tiang, tanpa kabel ke panel terpisah. Tiang tidak termasuk.</em></p>';

        return [
            // ---------------------------------------------------------------- ICOM IC-AIOM
            [
                'sku' => 'ICOM-IC-AIOM', 'slug' => 'lampu-jalan-all-in-one-icom-ic-aiom', 'brand' => 'icom', 'category' => 'aio',
                'name' => 'Lampu Jalan Tenaga Surya All-in-One ICOM IC-AIOM 60W / 80W / 100W', 'model' => 'IC-AIOM', 'option' => 'Daya',
                'warranty' => 'Garansi 1 tahun', 'featured' => true, 'meta_tail' => 'PJU Tenaga Surya Hemat',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, icom, ic-aiom, lampu solar 60w, 80w, 100w, lampu jalan solar cell',
                'short' => 'PJU all-in-one ICOM IC-AIOM: LED Philips 150 lm/W, panel mono, baterai LiFePO4, dimmer otomatis, IP65. Pilihan 60W / 80W / 100W. Harga khusus transaksi cash. Garansi 1 tahun.',
                'description' => <<<'HTML'
<p><strong>ICOM IC-AIOM</strong> — lampu jalan tenaga surya all-in-one ekonomis untuk jalan lingkungan, perumahan, area parkir, dan pedesaan. Panel surya monocrystalline, baterai LiFePO4, dan LED Philips menyatu dalam satu bodi aluminium alloy IP65.</p>
<ul>
<li>💡 LED chip Philips, 150 lm/W, sudut sinar 120°, 6000K, umur 50.000 jam</li>
<li>🔋 Baterai LiFePO4 (36Ah / 46Ah / 60Ah), 5–8 tahun; pengisian 8 jam matahari, menyala 12 jam penuh</li>
<li>🌙 Sistem dimmer otomatis — hemat energi di tengah malam</li>
<li>🛠️ Bodi aluminium alloy, IP65, tinggi pasang 5–9 m, jarak antar tiang 15–25 m</li>
</ul>
<p><strong>Catatan: harga tipe AIOM khusus transaksi cash.</strong></p>
HTML
                .$aioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>IC-AIOM60</td><td>IC-AIOM80</td><td>IC-AIOM100</td></tr>
<tr><th>Daya LED</th><td>60 W</td><td>80 W</td><td>100 W</td></tr>
<tr><th>LED Chip</th><td colspan="3">Philips, 150 lm/W, sudut 120°, 6000K, 50.000 jam</td></tr>
<tr><th>Panel Surya</th><td>Mono 70 W</td><td>Mono 90 W</td><td>Mono 120 W</td></tr>
<tr><th>Baterai LiFePO4</th><td>36 Ah</td><td>46 Ah</td><td>60 Ah</td></tr>
<tr><th>Pengisian / Nyala</th><td colspan="3">8 jam matahari / 12 jam mode penuh</td></tr>
<tr><th>Sistem</th><td colspan="3">Dimmer otomatis</td></tr>
<tr><th>Tinggi Pasang</th><td>5–7 m</td><td>6–8 m</td><td>8–9 m</td></tr>
<tr><th>Jarak Antar Tiang</th><td colspan="3">15–25 m</td></tr>
<tr><th>Material / Proteksi</th><td colspan="3">Aluminium alloy / IP65</td></tr>
<tr><th>Berat Kotor</th><td>10,5 kg</td><td>14 kg</td><td>21,3 kg</td></tr>
<tr><th>Ukuran Dus</th><td>102×41×18 cm</td><td>114×41×18 cm</td><td>136×41×18 cm</td></tr>
<tr><th>Garansi</th><td colspan="3">1 tahun</td></tr>
</tbody></table>
HTML,
                'variants' => [
                    ['sku' => 'ICOM-IC-AIOM60', 'name' => '60W', 'cost' => 1300000, 'weight' => 10500, 'dims' => [102, 41, 18], 'image' => 'ic-aiom60.jpg'],
                    ['sku' => 'ICOM-IC-AIOM80', 'name' => '80W', 'cost' => 1650000, 'weight' => 14000, 'dims' => [114, 41, 18], 'image' => 'ic-aiom80.jpg'],
                    ['sku' => 'ICOM-IC-AIOM100', 'name' => '100W', 'cost' => 1815000, 'weight' => 21300, 'dims' => [136, 41, 18], 'image' => 'ic-aiom100.jpg'],
                ],
            ],

            // ---------------------------------------------------------------- ICOM AIO sensor/dimming (tanpa brosur)
            [
                'sku' => 'ICOM-AIO-SENSOR', 'slug' => 'lampu-jalan-all-in-one-icom-aio-sensor-dimming', 'brand' => 'icom', 'category' => 'aio',
                'name' => 'Lampu Jalan Tenaga Surya All-in-One ICOM AIO Sensor / Dimming 60W / 80W', 'model' => 'AIO', 'option' => 'Tipe',
                'warranty' => 'Garansi 1 tahun', 'meta_tail' => 'PJU Tenaga Surya dengan Sensor Gerak',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, icom aio, sensor gerak, dimming, 60w, 80w, 12.8v, lampu jalan solar',
                'short' => 'PJU all-in-one ICOM AIO dengan sensor gerak & dimming: 60W 6V (4 strip LED), 80W 6V (5 strip), atau 80W 12,8V (4 strip, baterai tegangan tinggi). Garansi 1 tahun.',
                'description' => <<<'HTML'
<p><strong>ICOM AIO Sensor / Dimming</strong> — lampu jalan all-in-one dengan <strong>sensor gerak (PIR) dan dimming</strong>: terang penuh saat ada aktivitas, meredup otomatis saat sepi sehingga baterai lebih awet dan lampu tetap menyala sampai pagi.</p>
<ul>
<li>💡 Pilihan 60W (4 strip LED) atau 80W (5 strip LED) sistem 6V</li>
<li>🔋 Versi <strong>12,8V</strong> 80W (4 strip) — baterai tegangan lebih tinggi, arus lebih kecil, lebih efisien untuk daya besar</li>
<li>🌙 Sensor gerak + dimming otomatis, nyala/mati otomatis mengikuti cahaya</li>
<li>🛠️ Bodi aluminium, tahan cuaca, pasang di tiang tanpa kabel panel terpisah</li>
</ul>
<p>Spesifikasi detail (lumen, kapasitas baterai, dimensi) menyusul dari pabrikan — hubungi kami untuk datasheet terbaru.</p>
HTML
                .$aioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>AIO 60W 6V</td><td>AIO 80W 6V</td><td>AIO 80W 12,8V</td></tr>
<tr><th>Daya LED</th><td>60 W</td><td>80 W</td><td>80 W</td></tr>
<tr><th>Strip LED</th><td>4 strip</td><td>5 strip</td><td>4 strip</td></tr>
<tr><th>Sistem</th><td>6 V</td><td>6 V</td><td>12,8 V</td></tr>
<tr><th>Fitur</th><td colspan="3">Sensor gerak (PIR) + dimming, nyala/mati otomatis</td></tr>
<tr><th>Garansi</th><td colspan="3">1 tahun</td></tr>
</tbody></table>
HTML,
                'variants' => [
                    ['sku' => 'ICOM-AIO-60-6V', 'name' => '60W 6V · 4 Strip', 'cost' => 2850000, 'weight' => 12000, 'dims' => [105, 40, 18]],  // estimasi
                    ['sku' => 'ICOM-AIO-80-6V', 'name' => '80W 6V · 5 Strip', 'cost' => 3550000, 'weight' => 14000, 'dims' => [115, 40, 18]],  // estimasi
                    ['sku' => 'ICOM-AIO-80-12V', 'name' => '80W 12,8V · 4 Strip', 'cost' => 4228000, 'weight' => 16000, 'dims' => [115, 40, 18]], // estimasi
                ],
            ],

            // ---------------------------------------------------------------- ICOM IC-TEEN
            [
                'sku' => 'ICOM-IC-TEEN', 'slug' => 'lampu-jalan-all-in-one-icom-ic-teen', 'brand' => 'icom', 'category' => 'aio',
                'name' => 'Lampu Jalan Tenaga Surya All-in-One ICOM IC-TEEN 90W – 180W', 'model' => 'IC-TEEN', 'option' => 'Daya',
                'warranty' => 'Garansi 1 tahun', 'featured' => true, 'meta_tail' => 'PJU Tenaga Surya 165 lm/W',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, icom, ic-teen, 90w, 120w, 150w, 180w, lampu jalan solar jalan raya',
                'short' => 'PJU all-in-one ICOM IC-TEEN: LED Philips 165 lm/W sudut 135°, panel mono 6V, baterai LiFePO4 60–90Ah, dimmer, IP65. Pilihan 90W / 120W / 150W / 180W untuk jalan utama hingga tinggi 12 m.',
                'description' => <<<'HTML'
<p><strong>ICOM IC-TEEN</strong> — seri all-in-one berdaya besar untuk jalan utama, kawasan industri, dan area publik. Modul LED Philips 165 lm/W dengan sudut sinar lebar 135° memberi cakupan merata; baterai LiFePO4 besar menjamin nyala 12 jam penuh.</p>
<ul>
<li>💡 LED chip Philips, 165 lm/W, sudut 135°, 6000K, umur 55.000 jam</li>
<li>🔋 Baterai LiFePO4 3,2V 60–90 Ah, 5–8 tahun; pengisian 8 jam, nyala 12 jam penuh</li>
<li>☀️ Panel surya monocrystalline 100–190 W (6V)</li>
<li>🌙 Dimmer otomatis; bodi aluminium alloy IP65; tinggi pasang 5–12 m</li>
</ul>
HTML
                .$aioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>IC-TEEN90</td><td>IC-TEEN120</td><td>IC-TEEN150</td><td>IC-TEEN180</td></tr>
<tr><th>Daya LED</th><td>90 W</td><td>120 W</td><td>150 W</td><td>180 W</td></tr>
<tr><th>LED Chip</th><td colspan="4">Philips, 165 lm/W, sudut 135°, 6000K, 55.000 jam</td></tr>
<tr><th>Panel Surya</th><td>Mono 100 W 6V</td><td>Mono 130 W 6V</td><td>Mono 160 W 6V</td><td>Mono 190 W 6V</td></tr>
<tr><th>Baterai LiFePO4</th><td>60 Ah 3,2V</td><td>70 Ah 3,2V</td><td>80 Ah 3,2V</td><td>90 Ah 3,2V</td></tr>
<tr><th>Pengisian / Nyala</th><td colspan="4">8 jam matahari / 12 jam mode penuh</td></tr>
<tr><th>Sistem</th><td colspan="4">Dimmer otomatis</td></tr>
<tr><th>Tinggi Pasang</th><td>5–7 m</td><td>6–8 m</td><td>8–9 m</td><td>9–12 m</td></tr>
<tr><th>Jarak Antar Tiang</th><td colspan="4">15–25 m</td></tr>
<tr><th>Material / Proteksi</th><td colspan="4">Aluminium alloy / IP65</td></tr>
<tr><th>Berat Kotor</th><td>10,7 kg</td><td>11,8 kg</td><td>12,8 kg</td><td>14,1 kg</td></tr>
<tr><th>Ukuran Dus</th><td>87×37×10 cm</td><td>107,3×37×10 cm</td><td>119,2×37×10 cm</td><td>141,7×37×10 cm</td></tr>
<tr><th>Garansi</th><td colspan="4">1 tahun</td></tr>
</tbody></table>
HTML,
                'variants' => [
                    ['sku' => 'ICOM-IC-TEEN90', 'name' => '90W', 'cost' => 2098000, 'weight' => 10700, 'dims' => [87, 37, 10], 'image' => 'ic-teen90.jpg'],
                    ['sku' => 'ICOM-IC-TEEN120', 'name' => '120W', 'cost' => 2455000, 'weight' => 11800, 'dims' => [107.3, 37, 10], 'image' => 'ic-teen120.jpg'],
                    ['sku' => 'ICOM-IC-TEEN150', 'name' => '150W', 'cost' => 2740000, 'weight' => 12800, 'dims' => [119.2, 37, 10], 'image' => 'ic-teen150.jpg'],
                    ['sku' => 'ICOM-IC-TEEN180', 'name' => '180W', 'cost' => 3070000, 'weight' => 14100, 'dims' => [141.7, 37, 10], 'image' => 'ic-teen180.jpg'],
                ],
            ],

            // ---------------------------------------------------------------- SOLARI SL-MW
            [
                'sku' => 'SOLARI-SL-MW', 'slug' => 'lampu-jalan-all-in-one-solari-sl-mw', 'brand' => 'solari', 'category' => 'aio',
                'name' => 'Lampu Jalan Tenaga Surya All-in-One SOLARI SL-MW 80W / 100W / 120W', 'model' => 'SL-MW', 'option' => 'Daya',
                'warranty' => 'Garansi 3 tahun', 'meta_tail' => 'PJU Tenaga Surya Garansi 3 Tahun',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, solari, sl-mw, 80w, 100w, 120w, lifepo4, ip66, garansi 3 tahun',
                'short' => 'PJU all-in-one SOLARI SL-MW: bodi aluminium 6062 pull-up, LED Philips SMD 38×38, baterai LiFePO4 82–128Ah, IP66, tahan hujan 3 hari. Pilihan 80W / 100W / 120W. Garansi 3 tahun.',
                'description' => <<<'HTML'
<p><strong>SOLARI SL-MW</strong> — PJU all-in-one kelas proyek dengan bodi <strong>aluminium 6062 pull-up</strong> yang kokoh dan garansi resmi 3 tahun. Baterai LiFePO4 kapasitas besar membuat lampu tetap menyala hingga 3 hari saat hujan terus-menerus.</p>
<ul>
<li>💡 LED Philips chip SMD 38×38, 14.080–21.166 lumen, sudut sinar 120°×60°</li>
<li>🔋 Baterai LiFePO4 3,2V 82 / 102 / 128 Ah; nyala 8–12 jam; cadangan hujan 3 hari</li>
<li>☀️ Panel surya 115 / 155 / 195 Wp (6V)</li>
<li>🛠️ IP66, suhu kerja −20 s/d +45 °C, warna putih / warm white (WH/WWH); tinggi pasang 7–12 m</li>
</ul>
HTML
                .$aioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>SL-MW80</td><td>SL-MW100</td><td>SL-MW120</td></tr>
<tr><th>Daya LED</th><td>80 W</td><td>100 W</td><td>120 W</td></tr>
<tr><th>Lumen</th><td>14.080 lm</td><td>17.638 lm</td><td>21.166 lm</td></tr>
<tr><th>Panel Surya (maks.)</th><td>115 Wp 6V</td><td>155 Wp 6V</td><td>195 Wp 6V</td></tr>
<tr><th>Baterai</th><td>LiFePO4 82Ah 3,2V</td><td>LiFePO4 102Ah 3,2V</td><td>LiFePO4 128Ah 3,2V</td></tr>
<tr><th>Lama Nyala</th><td colspan="3">8–12 jam (opsional)</td></tr>
<tr><th>Cadangan Hujan</th><td colspan="3">3 hari</td></tr>
<tr><th>Sumber Cahaya</th><td colspan="3">Philips chip SMD 38×38, sudut 120°×60°</td></tr>
<tr><th>Bodi</th><td colspan="3">Aluminium 6062GB pull-up</td></tr>
<tr><th>Proteksi / Suhu</th><td colspan="3">IP66 / −20 s/d +45 °C</td></tr>
<tr><th>Tinggi Pasang</th><td>7–9 m</td><td>8–10 m</td><td>9–12 m</td></tr>
<tr><th>Ukuran Produk</th><td>1020×350×135 mm</td><td>1160×350×135 mm</td><td>1350×350×135 mm</td></tr>
<tr><th>Berat Kotor</th><td>16 kg</td><td>16,7 kg</td><td>17,8 kg</td></tr>
<tr><th>Garansi</th><td colspan="3">3 tahun</td></tr>
</tbody></table>
HTML,
                'variants' => [
                    ['sku' => 'SOLARI-SL-MW80', 'name' => '80W', 'cost' => 1990000, 'weight' => 16000, 'dims' => [105, 38, 16], 'image' => 'sl-mw80.jpg'],   // dus estimasi dari ukuran produk
                    ['sku' => 'SOLARI-SL-MW100', 'name' => '100W', 'cost' => 2210000, 'weight' => 16700, 'dims' => [119, 38, 16], 'image' => 'sl-mw100.jpg'],
                    ['sku' => 'SOLARI-SL-MW120', 'name' => '120W', 'cost' => 2535000, 'weight' => 17800, 'dims' => [138, 38, 16], 'image' => 'sl-mw120.jpg'],
                ],
                'brosur' => ['Brosur SOLARI SL-MW80 (PDF)' => 'sl-mw80.pdf', 'Brosur SOLARI SL-MW100 (PDF)' => 'sl-mw100.pdf', 'Brosur SOLARI SL-MW120 (PDF)' => 'sl-mw120.pdf'],
            ],

            // ---------------------------------------------------------------- LEIND LI-RON
            [
                'sku' => 'LEIND-LI-RON', 'slug' => 'lampu-jalan-all-in-one-leind-li-ron', 'brand' => 'leind', 'category' => 'aio',
                'name' => 'Lampu Jalan Tenaga Surya All-in-One LEIND LI-RON 85W / 110W / 128W', 'model' => 'LI-RON', 'option' => 'Daya',
                'warranty' => 'Garansi 1 tahun', 'meta_tail' => 'PJU Tenaga Surya 170 lm/W',
                'keywords' => 'pju tenaga surya, lampu jalan all in one, leind, li-ron, 85w, 110w, 128w, lampu jalan solar die cast',
                'short' => 'PJU all-in-one LEIND LI-RON: LED Epistar 170 lm/W, panel 96–166 W, baterai lithium 290–490 Wh, die-cast aluminium IP66, nyala 24–30 jam. Pilihan 85W / 110W / 128W.',
                'description' => <<<'HTML'
<p><strong>LEIND LI-RON</strong> — lampu jalan all-in-one dengan efikasi tinggi 170 lm/W dan bodi die-cast aluminium. Pengisian 4–8 jam, nyala hingga 24–30 jam, kontrol cahaya + kontrol pintar (PWM).</p>
<ul>
<li>💡 LED Epistar SMD2835, 170 lm/W, sudut 75°×155°, Ra &gt; 80</li>
<li>🔋 Baterai lithium 3,2V 290 / 370 / 490 Wh; pengisian 4–8 jam; nyala 24–30 jam</li>
<li>☀️ Panel surya 96 / 145 / 166 W</li>
<li>🛠️ Die-cast aluminium, IP66, −20 s/d +60 °C, kontroler PWM; tinggi pasang 5–10 m</li>
</ul>
HTML
                .$aioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>LI-RON85</td><td>LI-RON110</td><td>LI-RON128</td></tr>
<tr><th>Daya LED</th><td>85 W</td><td>110 W</td><td>128 W</td></tr>
<tr><th>Efikasi</th><td colspan="3">170 lm/W (Epistar SMD2835), Ra &gt; 80</td></tr>
<tr><th>Panel Surya</th><td>96 W</td><td>145 W</td><td>166 W</td></tr>
<tr><th>Baterai</th><td>Lithium 3,2V / 290 Wh</td><td>Lithium 3,2V / 370 Wh</td><td>Lithium 3,2V / 490 Wh</td></tr>
<tr><th>Pengisian / Nyala</th><td colspan="3">4–8 jam / 24–30 jam</td></tr>
<tr><th>Dimensi Lampu</th><td>1025×370×133 mm</td><td>1118×370×133 mm</td><td>1338×370×133 mm</td></tr>
<tr><th>Berat Bersih</th><td>9,6 kg</td><td>11 kg</td><td>12,9 kg</td></tr>
<tr><th>Ukuran Dus</th><td>1040×380×155 mm</td><td>1140×380×155 mm</td><td>1350×380×155 mm</td></tr>
<tr><th>Tinggi Pasang</th><td>5–7 m</td><td>6–8 m</td><td>7–10 m</td></tr>
<tr><th>Sudut Sinar</th><td colspan="3">75°×155°</td></tr>
<tr><th>Kontroler / Mode</th><td colspan="3">PWM / kontrol cahaya &amp; kontrol pintar</td></tr>
<tr><th>Material / Proteksi</th><td colspan="3">Die-cast aluminium / IP66, −20 s/d +60 °C</td></tr>
</tbody></table>
HTML,
                'variants' => [
                    ['sku' => 'LEIND-LI-RON85', 'name' => '85W', 'cost' => 2285000, 'weight' => 10500, 'dims' => [104, 38, 15.5]],
                    ['sku' => 'LEIND-LI-RON110', 'name' => '110W', 'cost' => 2440000, 'weight' => 12000, 'dims' => [114, 38, 15.5]],
                    ['sku' => 'LEIND-LI-RON128', 'name' => '128W', 'cost' => 2820000, 'weight' => 14000, 'dims' => [135, 38, 15.5]],
                ],
                'image' => 'li-ron.jpg',
                'brosur' => ['Brosur LEIND LI-RON (PDF)' => 'li-ron.pdf'],
            ],

            // ---------------------------------------------------------------- LEIND LI-ZLW Mini
            [
                'sku' => 'LEIND-LI-ZLW', 'slug' => 'lampu-jalan-all-in-one-leind-zl-w-mini', 'brand' => 'leind', 'category' => 'aio',
                'name' => 'Lampu Jalan Tenaga Surya All-in-One Mini LEIND ZL-W 100W / 180W / 240W', 'model' => 'LI-ZLW', 'option' => 'Daya',
                'warranty' => 'Garansi 1 tahun', 'meta_tail' => 'PJU Tenaga Surya Mini Hemat',
                'keywords' => 'pju tenaga surya mini, lampu jalan all in one murah, leind, zl-w, zlw, 100w, 180w, 240w, lampu taman solar, lampu gang',
                'short' => 'PJU all-in-one mini LEIND ZL-W: ringkas dan ekonomis untuk gang, taman, dan halaman. LED Epistar, baterai lithium 60–128 Wh, nyala 20 jam, IP66. Pilihan 100W / 180W / 240W (daya nominal LED).',
                'description' => <<<'HTML'
<p><strong>LEIND ZL-W Mini</strong> — lampu jalan all-in-one ukuran ringkas dengan harga paling terjangkau. Cocok untuk penerangan gang, taman, halaman rumah, pos ronda, dan jalan lingkungan dengan tiang 5–10 m.</p>
<ul>
<li>💡 LED Epistar SMD2835, 100 lm/W, sudut 75°×155°, Ra &gt; 80</li>
<li>🔋 Baterai lithium 3,2V 60 / 96 / 128 Wh; pengisian 4–8 jam; nyala 20 jam</li>
<li>☀️ Panel surya 46 / 66 / 96 W terintegrasi</li>
<li>🛠️ Die-cast aluminium, IP66, kontroler PWM, kontrol cahaya &amp; kontrol pintar</li>
</ul>
HTML
                .$aioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>ZL-W 100</td><td>ZL-W 180 <small>(brosur: LI-ZLW150)</small></td><td>ZL-W 240</td></tr>
<tr><th>Efikasi</th><td colspan="3">100 lm/W (Epistar SMD2835), Ra &gt; 80</td></tr>
<tr><th>Panel Surya</th><td>46 W</td><td>66 W</td><td>96 W</td></tr>
<tr><th>Baterai</th><td>Lithium 3,2V / 60 Wh</td><td>Lithium 3,2V / 96 Wh</td><td>Lithium 3,2V / 128 Wh</td></tr>
<tr><th>Pengisian / Nyala</th><td colspan="3">4–8 jam / 20 jam</td></tr>
<tr><th>Tinggi Pasang</th><td>5–7 m</td><td>6–8 m</td><td>7–10 m</td></tr>
<tr><th>Sudut Sinar</th><td colspan="3">75°×155°</td></tr>
<tr><th>Kontroler / Mode</th><td colspan="3">PWM / kontrol cahaya &amp; kontrol pintar</td></tr>
<tr><th>Material / Proteksi</th><td colspan="3">Die-cast aluminium / IP66, −20 s/d +60 °C</td></tr>
</tbody></table>
HTML,
                'variants' => [
                    ['sku' => 'LEIND-LI-ZLW100', 'name' => '100W', 'cost' => 950000, 'weight' => 4000, 'dims' => [62, 22, 10]],   // estimasi
                    ['sku' => 'LEIND-LI-ZLW180', 'name' => '180W', 'cost' => 1280000, 'weight' => 5000, 'dims' => [78, 22, 10]],  // estimasi
                    ['sku' => 'LEIND-LI-ZLW240', 'name' => '240W', 'cost' => 1750000, 'weight' => 6500, 'dims' => [95, 22, 10]],  // estimasi
                ],
                'image' => 'li-zlw.jpg',
                'brosur' => ['Brosur LEIND LI-ZLW (PDF)' => 'li-zlw.pdf'],
            ],

            // ---------------------------------------------------------------- LEIND LI-CITY (two-in-one)
            [
                'sku' => 'LEIND-LI-CITY', 'slug' => 'lampu-jalan-two-in-one-leind-li-city', 'brand' => 'leind', 'category' => 'tio',
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One LEIND LI-CITY 50W / 100W / 150W + Panel Surya', 'model' => 'LI-CITY', 'option' => 'Paket',
                'warranty' => 'Garansi 1 tahun', 'featured' => true, 'meta_tail' => 'PJU Two-in-One SNI',
                'keywords' => 'pju tenaga surya two in one, lampu jalan solar panel terpisah, leind, li-city, 50w, 100w, 150w, sni, mppt, 12.8v',
                'short' => 'PJU two-in-one LEIND LI-CITY (SNI): lampu LED Epistar 140–150 lm/W + panel surya terpisah, kontroler MPPT, die-cast aluminium IP65, nyala 24–30 jam. Paket 50W+100Wp, 100W+135Wp, 150W+160Wp, atau 150W 12,8V+200Wp.',
                'description' => <<<'HTML'
<p><strong>LEIND LI-CITY</strong> — lampu jalan two-in-one bersertifikat <strong>SNI</strong>: lampu dan panel surya terpisah sehingga panel bisa diarahkan optimal ke matahari dan lampu tetap menyala 24–30 jam. Kontroler MPPT menyerap energi lebih banyak dibanding PWM.</p>
<ul>
<li>💡 LED Epistar SMD5050, 140–150 lm/W, sudut 70°×145°, Ra &gt; 80</li>
<li>🔋 Baterai lithium 150 Wh / 250 Wh / 12,8V 45 Ah; pengisian 8–10 jam; nyala 24–30 jam</li>
<li>☀️ Panel surya 18V terpisah: 100 / 135 / 160 / 200 Wp (sesuai paket)</li>
<li>🛠️ Die-cast aluminium, IP65, −20 s/d +60 °C, MPPT, kontrol cahaya &amp; pintar; tinggi pasang 5–12 m</li>
</ul>
HTML
                .$tioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Paket</th><td>LI-CITY-50 + PV 100Wp</td><td>LI-CITY-100 + PV 135Wp</td><td>LI-CITY-150 + PV 160Wp</td><td>LI-CITY-150 (12,8V) + PV 200Wp</td></tr>
<tr><th>Daya Lampu</th><td>50 W</td><td>100 W</td><td>150 W</td><td>150 W</td></tr>
<tr><th>Efikasi</th><td colspan="4">140–150 lm/W (Epistar SMD5050), Ra &gt; 80</td></tr>
<tr><th>Panel Surya Disarankan</th><td>18V 50–150 W</td><td>18V 80–300 W</td><td colspan="2">18V 150–250 W</td></tr>
<tr><th>Baterai</th><td>Lithium 3,2V / 150 Wh</td><td>Lithium 3,2V / 250 Wh</td><td>Lithium (versi standar)</td><td>Lithium 12,8V / 45 Ah</td></tr>
<tr><th>Dimensi Lampu</th><td>635×270×155 mm</td><td>820×270×155 mm</td><td colspan="2">995×270×155 mm</td></tr>
<tr><th>Berat Lampu</th><td>6 kg</td><td>8 kg</td><td colspan="2">11 kg</td></tr>
<tr><th>Dus Lampu</th><td>650×285×175 mm</td><td>840×285×175 mm</td><td colspan="2">1015×285×175 mm</td></tr>
<tr><th>Pengisian / Nyala</th><td colspan="4">8–10 jam / 24–30 jam</td></tr>
<tr><th>Tinggi Pasang</th><td>5–7 m</td><td>7–10 m</td><td colspan="2">9–12 m</td></tr>
<tr><th>Sudut Sinar</th><td colspan="4">70°×145°</td></tr>
<tr><th>Kontroler / Mode</th><td colspan="4">MPPT / kontrol cahaya &amp; kontrol pintar</td></tr>
<tr><th>Material / Proteksi</th><td colspan="4">Die-cast aluminium / IP65, −20 s/d +60 °C</td></tr>
<tr><th>Sertifikasi</th><td colspan="4">SNI</td></tr>
</tbody></table>
HTML,
                'variants' => [ // berat = lampu + panel (estimasi panel 100Wp 7 kg, 135Wp 9 kg, 160Wp 11 kg, 200Wp 13 kg)
                    ['sku' => 'LEIND-LI-CITY50-100', 'name' => '50W + PV 100Wp', 'cost' => 2285000, 'weight' => 13000, 'dims' => [100, 67, 22]],
                    ['sku' => 'LEIND-LI-CITY100-135', 'name' => '100W + PV 135Wp', 'cost' => 2785000, 'weight' => 17000, 'dims' => [115, 67, 22]],
                    ['sku' => 'LEIND-LI-CITY150-160', 'name' => '150W + PV 160Wp', 'cost' => 3450000, 'weight' => 22000, 'dims' => [130, 70, 22]],
                    ['sku' => 'LEIND-LI-CITY150-12V-200', 'name' => '150W 12,8V + PV 200Wp', 'cost' => 5500000, 'weight' => 24000, 'dims' => [150, 70, 22]],
                ],
                'image' => 'li-city.jpg',
            ],

            // ---------------------------------------------------------------- ICOM IC-YIN (two-in-one)
            [
                'sku' => 'ICOM-IC-YIN', 'slug' => 'lampu-jalan-two-in-one-icom-ic-yin', 'brand' => 'icom', 'category' => 'tio',
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One ICOM IC-YIN 40W / 60W / 80W + Panel Surya', 'model' => 'IC-YIN', 'option' => 'Paket',
                'warranty' => 'Garansi 3 tahun', 'meta_tail' => 'PJU Two-in-One Garansi 3 Tahun',
                'keywords' => 'pju tenaga surya two in one, icom, ic-yin, 40w, 60w, 80w, lampu jalan solar panel terpisah, garansi 3 tahun',
                'short' => 'PJU two-in-one ICOM IC-YIN: lampu LED Philips 6.390–12.780 lm IP66 + panel surya 100/135 Wp, baterai LiFePO4 8V 368–683 Wh, dimming, kabel 6 m. Garansi 3 tahun. Paket 40W, 60W, 80W.',
                'description' => <<<'HTML'
<p><strong>ICOM IC-YIN</strong> — lampu jalan two-in-one dengan lampu aluminium IP66 dan panel surya terpisah yang bisa diarahkan optimal. Baterai LiFePO4 8V di dalam lampu, cadangan hujan 3 hari, dan <strong>garansi 3 tahun</strong>.</p>
<ul>
<li>💡 LED Philips 30×30 (90 / 120 / 150 pcs), &gt;6.390 / 9.585 / 12.780 lm, 6500K</li>
<li>🔋 Baterai LiFePO4 8V 368 / 460 / 683 Wh; pengisian 5–6 jam; cadangan hujan 3 hari</li>
<li>☀️ Panel surya 100 Wp (40W) atau 135 Wp (60W &amp; 80W); kontroler 15A 6–12V; kabel 6 m</li>
<li>🛠️ Aluminium IP66, warna hitam/abu, dimming; tinggi pasang 7–9 m; umur 5–7 tahun</li>
</ul>
HTML
                .$tioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Paket</th><td>IC-YIN40 + PV 100Wp</td><td>IC-YIN60 + PV 135Wp</td><td>IC-YIN80 + PV 135Wp</td></tr>
<tr><th>Daya Lampu</th><td>40 W</td><td>60 W</td><td>80 W</td></tr>
<tr><th>Lumen</th><td>&gt; 6.390 lm</td><td>&gt; 9.585 lm</td><td>&gt; 12.780 lm</td></tr>
<tr><th>LED</th><td>Philips 30×30, 90 pcs</td><td>Philips 30×30, 120 pcs</td><td>Philips 30×30, 150 pcs</td></tr>
<tr><th>Panel Surya Disarankan</th><td>100 Wp</td><td>100–135 Wp</td><td>135–160 Wp</td></tr>
<tr><th>Baterai LiFePO4</th><td>368 Wh 8V</td><td>460 Wh 8V</td><td>683 Wh 8V</td></tr>
<tr><th>Pengisian</th><td colspan="3">5–6 jam; cadangan hujan 3 hari</td></tr>
<tr><th>Kontroler</th><td colspan="3">15A, 6–12V; kabel 6 m</td></tr>
<tr><th>CCT / Proteksi</th><td colspan="3">6500K / IP66, aluminium, hitam/abu</td></tr>
<tr><th>Tinggi Pasang</th><td colspan="3">7–9 m</td></tr>
<tr><th>Sistem</th><td colspan="3">Dimming</td></tr>
<tr><th>Garansi / Umur</th><td colspan="3">3 tahun / 5–7 tahun</td></tr>
</tbody></table>
HTML,
                'variants' => [ // berat estimasi: lampu 7–9 kg + panel 100Wp 7 kg / 135Wp 9 kg
                    ['sku' => 'ICOM-IC-YIN40', 'name' => '40W + PV 100Wp', 'cost' => 2055000, 'weight' => 14000, 'dims' => [100, 67, 20], 'image' => 'ic-yin40.jpg'],
                    ['sku' => 'ICOM-IC-YIN60', 'name' => '60W + PV 135Wp', 'cost' => 2365000, 'weight' => 17000, 'dims' => [115, 67, 20], 'image' => 'ic-yin60.jpg'],
                    ['sku' => 'ICOM-IC-YIN80', 'name' => '80W + PV 135Wp', 'cost' => 2695000, 'weight' => 18000, 'dims' => [115, 67, 20], 'image' => 'ic-yin80.jpg'],
                ],
                'brosur' => ['Brosur ICOM IC-YIN40 (PDF)' => 'ic-yin40.pdf', 'Brosur ICOM IC-YIN60 (PDF)' => 'ic-yin60.pdf', 'Brosur ICOM IC-YIN80 (PDF)' => 'ic-yin80.pdf'],
            ],

            // ---------------------------------------------------------------- ICOM IC-FIN (two-in-one)
            [
                'sku' => 'ICOM-IC-FIN', 'slug' => 'lampu-jalan-two-in-one-icom-ic-fin', 'brand' => 'icom', 'category' => 'tio',
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One ICOM IC-FIN 100W / 120W + Panel Surya', 'model' => 'IC-FIN', 'option' => 'Paket',
                'warranty' => 'Garansi 3 tahun', 'meta_tail' => 'PJU Two-in-One 15.200 lm',
                'keywords' => 'pju tenaga surya two in one, icom, ic-fin, 100w, 120w, lampu jalan solar panel terpisah, 15200 lumen, garansi 3 tahun',
                'short' => 'PJU two-in-one ICOM IC-FIN: lampu LED Philips &gt;15.200 lm IP66 + panel surya 135/200 Wp, baterai LiFePO4 8V 763–976 Wh, dimming, kabel 6 m. Garansi 3 tahun. Paket 100W, 120W.',
                'description' => <<<'HTML'
<p><strong>ICOM IC-FIN</strong> — seri two-in-one berdaya besar untuk jalan utama dan kawasan: lampu aluminium IP66 dengan 150 LED Philips (&gt;15.200 lm), baterai LiFePO4 8V kapasitas besar, cadangan hujan 3 hari, garansi 3 tahun.</p>
<ul>
<li>💡 LED Philips 30×30, 150 pcs, &gt;15.200 lm, 6500K</li>
<li>🔋 Baterai LiFePO4 8V 763 Wh (100W) / 976 Wh (120W); pengisian 5–6 jam; cadangan hujan 3 hari</li>
<li>☀️ Panel surya 135 Wp (100W) / 200 Wp (120W); kontroler 15A 6–12V; kabel 6 m</li>
<li>🛠️ Aluminium IP66, hitam/abu, dimming; tinggi pasang 7–9 m; umur 5–7 tahun</li>
</ul>
HTML
                .$tioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Paket</th><td>IC-FIN100 + PV 135Wp</td><td>IC-FIN120 + PV 200Wp</td></tr>
<tr><th>Daya Lampu</th><td>100 W</td><td>120 W</td></tr>
<tr><th>Lumen</th><td colspan="2">&gt; 15.200 lm (Philips 30×30, 150 pcs)</td></tr>
<tr><th>Panel Surya Disarankan</th><td>160–200 Wp</td><td>200–300 Wp</td></tr>
<tr><th>Baterai LiFePO4</th><td>763 Wh 8V</td><td>976 Wh 8V</td></tr>
<tr><th>Pengisian</th><td colspan="2">5–6 jam; cadangan hujan 3 hari</td></tr>
<tr><th>Kontroler</th><td colspan="2">15A, 6–12V; kabel 6 m</td></tr>
<tr><th>CCT / Proteksi</th><td colspan="2">6500K / IP66, aluminium, hitam/abu</td></tr>
<tr><th>Tinggi Pasang</th><td colspan="2">7–9 m</td></tr>
<tr><th>Sistem</th><td colspan="2">Dimming</td></tr>
<tr><th>Garansi / Umur</th><td colspan="2">3 tahun / 5–7 tahun</td></tr>
</tbody></table>
HTML,
                'variants' => [ // berat estimasi: lampu 10–12 kg + panel 135Wp 9 kg / 200Wp 13 kg
                    ['sku' => 'ICOM-IC-FIN100', 'name' => '100W + PV 135Wp', 'cost' => 2890000, 'weight' => 19000, 'dims' => [120, 67, 22], 'image' => 'ic-fin100.jpg'],
                    ['sku' => 'ICOM-IC-FIN120', 'name' => '120W + PV 200Wp', 'cost' => 3295000, 'weight' => 25000, 'dims' => [150, 70, 22], 'image' => 'ic-fin120.jpg'],
                ],
                'brosur' => ['Brosur ICOM IC-FIN100 (PDF)' => 'ic-fin100.pdf', 'Brosur ICOM IC-FIN120 (PDF)' => 'ic-fin120.pdf'],
            ],

            // ---------------------------------------------------------------- SUNYO SY-BEK (two-in-one)
            [
                'sku' => 'SUNYO-SY-BEK', 'slug' => 'lampu-jalan-two-in-one-sunyo-sy-bek', 'brand' => 'sunyo', 'category' => 'tio',
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One SUNYO SY-BEK 60W / 90W / 110W + Panel Surya', 'model' => 'SY-BEK', 'option' => 'Paket',
                'warranty' => 'Garansi 4 tahun', 'meta_tail' => 'PJU Two-in-One Lumileds Garansi 4 Tahun',
                'keywords' => 'pju tenaga surya two in one, sunyo, sy-bek, 60w, 90w, 110w, lumileds luxeon, ip66 ik09, garansi 4 tahun',
                'short' => 'PJU two-in-one SUNYO SY-BEK: LED Lumileds LUXEON 5050 &gt;160 lm/W, die-cast IP66 IK09, baterai 546–966 Wh 10V, SCC 20A, panel surya 100/135/200 Wp. Garansi 4 tahun. Paket 60W, 90W, 110W.',
                'description' => <<<'HTML'
<p><strong>SUNYO SY-BEK</strong> — lampu jalan two-in-one kelas premium: LED <strong>Lumileds LUXEON 5050</strong> (&gt;160 lm/W, L70 &gt; 54.000 jam), bodi die-cast aluminium IP66 / IK09 tahan benturan, distribusi cahaya Type I–IV, dan <strong>garansi 4 tahun</strong>.</p>
<ul>
<li>💡 Lumileds LUXEON 5050, &gt;160 lm/W, CCT 2700–6500K, Ra &gt; 70, &lt;5 SDCM</li>
<li>🔋 Baterai 546 / 760 / 966 Wh 10V; SCC 20A; input 12–40V</li>
<li>☀️ Panel surya 100 Wp (60W), 135 Wp (90W), 200 Wp (110W)</li>
<li>🛠️ Die-cast aluminium, IP66 IK09, Class I, −20 s/d +50 °C, tiang Ø40–60 mm</li>
</ul>
HTML
                .$tioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Paket</th><td>SY-BEK 60W + PV 100Wp</td><td>SY-BEK 90W + PV 135Wp</td><td>SY-BEK 110W + PV 200Wp</td></tr>
<tr><th>Daya Nominal</th><td>60 W</td><td>90 W</td><td>110 W</td></tr>
<tr><th>Tegangan Input</th><td colspan="3">12–40 V</td></tr>
<tr><th>Distribusi Cahaya</th><td colspan="3">Type I, II, III, IV</td></tr>
<tr><th>CCT / CRI</th><td colspan="3">2700–6500K / Ra &gt; 70, &lt;5 SDCM</td></tr>
<tr><th>LED</th><td colspan="3">Lumileds LUXEON 5050, &gt;160 lm/W, L70 &gt; 54.000 jam</td></tr>
<tr><th>Baterai</th><td>546 Wh 10V</td><td>760 Wh 10V</td><td>966 Wh 10V</td></tr>
<tr><th>SCC</th><td colspan="3">20 A</td></tr>
<tr><th>Panel Surya Minimal</th><td>&gt; 70 W</td><td>&gt; 100 W</td><td>&gt; 110 W</td></tr>
<tr><th>Proteksi</th><td colspan="3">IP66, IK09, Class I</td></tr>
<tr><th>Bodi / Suhu</th><td colspan="3">Die-cast aluminium / −20 s/d +50 °C</td></tr>
<tr><th>Diameter Tiang</th><td colspan="3">40–60 mm</td></tr>
<tr><th>Garansi</th><td colspan="3">4 tahun</td></tr>
</tbody></table>
HTML,
                'variants' => [ // berat estimasi: lampu 8–10 kg + panel 100Wp 7 / 135Wp 9 / 200Wp 13 kg
                    ['sku' => 'SUNYO-SY-BEK60', 'name' => '60W + PV 100Wp', 'cost' => 2150000, 'weight' => 15000, 'dims' => [100, 67, 22]],
                    ['sku' => 'SUNYO-SY-BEK90', 'name' => '90W + PV 135Wp', 'cost' => 2895000, 'weight' => 18000, 'dims' => [115, 67, 22]],
                    ['sku' => 'SUNYO-SY-BEK110', 'name' => '110W + PV 200Wp', 'cost' => 3275000, 'weight' => 23000, 'dims' => [150, 70, 22]],
                ],
                'image' => 'sy-bek.jpg',
                'brosur' => ['Brosur SUNYO SY-BEK (PDF)' => 'sy-bek.pdf'],
            ],

            // ---------------------------------------------------------------- LEIND LI-SLIM 100 (two-in-one, simple)
            [
                'sku' => 'LEIND-LI-SLIM100', 'slug' => 'lampu-jalan-two-in-one-leind-li-slim-100w', 'brand' => 'leind', 'category' => 'tio',
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One LEIND LI-SLIM 100W + Panel Surya 100Wp', 'model' => 'LI-SLIM100',
                'warranty' => 'Garansi 1 tahun', 'meta_tail' => 'PJU Two-in-One 12,8V',
                'keywords' => 'pju tenaga surya two in one, leind, li-slim, 100w, 12.8v, lampu jalan solar panel terpisah, philips smd3535',
                'short' => 'PJU two-in-one LEIND LI-SLIM 100W: LED Philips SMD3535 160 lm/W, baterai lithium 12,8V 308 Wh, die-cast IP66, nyala 20 jam + panel surya 100 Wp.',
                'description' => <<<'HTML'
<p><strong>LEIND LI-SLIM 100</strong> — lampu jalan two-in-one ramping dengan baterai sistem <strong>12,8V</strong> (308 Wh) dan LED Philips SMD3535 160 lm/W. Paket sudah termasuk panel surya 100 Wp.</p>
<ul>
<li>💡 LED Philips SMD3535, 160 lm/W, sudut 75°×155°, Ra &gt; 80</li>
<li>🔋 Baterai lithium 12,8V / 308 Wh; pengisian 4–8 jam; nyala 20 jam</li>
<li>☀️ Panel surya &gt; 100 W (paket: 100 Wp)</li>
<li>🛠️ Die-cast aluminium, IP66, −20 s/d +60 °C, PWM, kontrol cahaya &amp; pintar; tinggi pasang 5–7 m</li>
</ul>
HTML
                .$tioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>LI-SLIM100 + PV 100Wp</td></tr>
<tr><th>Daya Lampu</th><td>100 W</td></tr>
<tr><th>Efikasi</th><td>160 lm/W (Philips SMD3535), Ra &gt; 80</td></tr>
<tr><th>Panel Surya</th><td>&gt; 100 W (paket 100 Wp)</td></tr>
<tr><th>Baterai</th><td>Lithium 12,8V / 308 Wh</td></tr>
<tr><th>Pengisian / Nyala</th><td>4–8 jam / 20 jam</td></tr>
<tr><th>Tinggi Pasang</th><td>5–7 m</td></tr>
<tr><th>Sudut Sinar</th><td>75°×155°</td></tr>
<tr><th>Kontroler / Mode</th><td>PWM / kontrol cahaya &amp; kontrol pintar</td></tr>
<tr><th>Material / Proteksi</th><td>Die-cast aluminium / IP66, −20 s/d +60 °C</td></tr>
</tbody></table>
HTML,
                'cost' => 1999000, 'weight' => 15000, 'dims' => [100, 67, 22], // estimasi lampu 8 kg + panel 7 kg
                'image' => 'li-slim.jpg',
                'brosur' => ['Brosur LEIND LI-SLIM (PDF)' => 'li-slim.pdf'],
            ],

            // ---------------------------------------------------------------- LEIND LI-VILL 100 (two-in-one, simple)
            [
                'sku' => 'LEIND-LI-VILL100', 'slug' => 'lampu-jalan-two-in-one-leind-li-vill-100w', 'brand' => 'leind', 'category' => 'tio',
                'name' => 'Lampu Jalan Tenaga Surya Two-in-One LEIND LI-VILL 100W + Panel Surya 35Wp', 'model' => 'LI-VILL100',
                'warranty' => 'Garansi 1 tahun', 'meta_tail' => 'PJU Two-in-One Murah dengan Remote',
                'keywords' => 'pju tenaga surya murah, lampu jalan two in one, leind, li-vill, 100w, remote control, lampu solar desa, lampu jalan kampung',
                'short' => 'PJU two-in-one LEIND LI-VILL 100W: hemat untuk jalan kampung & halaman. LED 110–120 lm/W, baterai lithium 30.000 mAh, IP66, remote control, nyala 12 jam + panel surya 35 Wp. Garansi 1 tahun.',
                'description' => <<<'HTML'
<p><strong>LEIND LI-VILL 100</strong> — penerangan cerdas, hemat energi, dan ramah lingkungan untuk jalan desa, taman, dan halaman. Dilengkapi <strong>remote control</strong> untuk mengatur mode nyala, bodi die-cast aluminium IP66 tahan air dan debu.</p>
<ul>
<li>💡 LED 100W, 110–120 lm/W (chip Philips / Bridgelux / Epistar / BMTC), sudut 120°, Ra &gt; 80</li>
<li>🔋 Baterai lithium 3,2V 30.000 mAh; pengisian 6–8 jam; nyala 12 jam</li>
<li>☀️ Panel surya 35 W (670×350 mm) terpisah</li>
<li>🛠️ Die-cast aluminium, IP66, −20 s/d +60 °C, kontrol cahaya + remote; tinggi pasang 4–6 m</li>
</ul>
HTML
                .$tioNote,
                'specifications' => <<<'HTML'
<table><tbody>
<tr><th>Tipe</th><td>LI-VILL100 + PV 35Wp</td></tr>
<tr><th>Daya Lampu</th><td>100 W</td></tr>
<tr><th>Efikasi</th><td>110–120 lm/W, Ra &gt; 80, sudut 120°</td></tr>
<tr><th>Panel Surya</th><td>35 W, 670×350 mm</td></tr>
<tr><th>Baterai</th><td>Lithium 3,2V / 30.000 mAh</td></tr>
<tr><th>Pengisian / Nyala</th><td>6–8 jam / 12 jam</td></tr>
<tr><th>Dimensi Lampu</th><td>596×252×93 mm, 5,5 kg</td></tr>
<tr><th>Ukuran Dus</th><td>710×140×360 mm</td></tr>
<tr><th>Tinggi Pasang</th><td>4–6 m</td></tr>
<tr><th>Mode Kerja</th><td>Kontrol cahaya + remote control</td></tr>
<tr><th>Material / Proteksi</th><td>Die-cast aluminium / IP66, −20 s/d +60 °C</td></tr>
<tr><th>Garansi</th><td>1 tahun</td></tr>
</tbody></table>
HTML,
                'cost' => 950000, 'weight' => 8500, 'dims' => [71, 36, 14], // lampu 5,5 kg + panel 35Wp ±3 kg
                'image' => 'li-vill100.jpg',
            ],
        ];
    }
}
