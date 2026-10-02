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
use Illuminate\Support\Facades\Storage;

/**
 * Pompa Air Tenaga Surya Hybrid LARENS 4PSS/6PSS + Kontroler (Okt 2026).
 * Hybrid = bisa dari panel surya (DC) dan PLN/genset (AC 90–240V). Brosur
 * memuat 23 model; daftar harga hanya per daya: 750 W (modal Rp 13.450.000)
 * dan 2200 W (modal Rp 16.458.000) — jadi satu produk bervarian, harga sama
 * untuk semua model dalam satu kelas daya, varian = kombinasi debit × head.
 * Harga jual = modal ÷ 0,75 bulat ke atas 10.000 (17.940.000 / 21.950.000).
 * Idempotent: produk/varian yang ada tidak ditimpa; berat & dimensi estimasi.
 */
class PompaHybridLarensSeeder extends Seeder
{
    private const COST = [750 => 13450000, 2200 => 16458000];

    /** [model, daya W, debit m³/jam, head m, outlet inch, DC Voc, pipa inch] dari brosur LARENS. */
    private const MODELS = [
        ['4PSS3.0/100-96/750-H', 750, '3,0', 100, '1,25"', '60–240 V', 4],
        ['4PSS5.5/70-96/750-H', 750, '5,5', 70, '1,25"', '60–240 V', 4],
        ['4PSS8.0/50-96/750-H', 750, '8,0', 50, '1,5"', '60–240 V', 4],
        ['4PSS15.0/30-96/750-H', 750, '15,0', 30, '2"', '60–240 V', 4],
        ['4PSS3.5/260-280/2200-H', 2200, '3,5', 260, '1,25"', '100–490 V', 4],
        ['4PSS6.0/190-280/2200-H', 2200, '6,0', 190, '1,25"', '100–490 V', 4],
        ['4PSS9.0/140-280/2200-H', 2200, '9,0', 140, '1,5"', '100–490 V', 4],
        ['4PSS17.0/100-280/2200-H', 2200, '17,0', 100, '2"', '100–490 V', 4],
        ['4PSS21.0/60-280/2200-H', 2200, '21,0', 60, '2"', '100–490 V', 4],
        ['6PSS25.0/45-280/2200-H', 2200, '25,0', 45, '3"', '100–490 V', 6],
    ];

    public function run(): void
    {
        $stock = app(StockService::class);
        $category = Category::where('slug', 'pompa-air-tenaga-surya-submersible')->first()
            ?? Category::where('slug', 'pompa-air-tenaga-surya')->first();
        $parent = Category::where('slug', 'pompa-air-tenaga-surya')->first();
        $brand = Brand::firstOrCreate(['slug' => 'larens'], [
            'name' => 'LARENS', 'is_active' => true, 'is_featured' => false, 'sort_order' => 60,
            'description' => 'Pompa air tenaga surya LARENS — Making Life Easier.', 'meta_title' => 'Produk LARENS — Pompa Air Tenaga Surya',
        ]);

        $rows = '';
        foreach (self::MODELS as [$model, $w, $flow, $head, $outlet, $voc]) {
            $rows .= "<tr><td>{$model}</td><td>{$w} W</td><td>{$flow} m³/jam</td><td>{$head} m</td><td>{$outlet}</td><td>{$voc}</td></tr>\n";
        }

        $existing = Product::where('sku', 'LARENS-PSS-HYBRID')->orWhere('slug', 'pompa-air-tenaga-surya-hybrid-larens-4pss-kontroler')->first();
        $product = $existing ?? Product::create([
            'slug' => 'pompa-air-tenaga-surya-hybrid-larens-4pss-kontroler',
            'sku' => 'LARENS-PSS-HYBRID',
            'name' => 'Pompa Air Tenaga Surya Hybrid LARENS 4PSS/6PSS 750W – 2200W + Kontroler',
            'category_id' => $category?->id,
            'brand_id' => $brand->id,
            'model' => '4PSS / 6PSS Hybrid',
            'product_type' => 'variable',
            'condition' => 'new',
            'short_description' => 'Pompa submersible tenaga surya HYBRID LARENS + kontroler: jalan dari panel surya (DC) maupun PLN/genset (AC 90–240 V). Pilihan 750 W (debit 3–15 m³/jam, head 30–100 m) dan 2200 W (debit 3,5–25 m³/jam, head 45–260 m).',
            'description' => <<<'HTML'
<p><strong>LARENS 4PSS/6PSS Hybrid</strong> — pompa air submersible (celup) tenaga surya dengan <strong>kontroler hybrid</strong>: siang memompa langsung dari panel surya (DC), dan saat mendung/malam otomatis memakai PLN atau genset (AC 90–240 V). Cocok untuk irigasi, perkebunan, peternakan, tandon desa, dan sumur bor.</p>
<ul>
<li>⚡ <strong>Hybrid AC/DC</strong>: input DC dari panel surya + input AC 90–240 V, pindah otomatis</li>
<li>💧 <strong>750 W</strong>: debit 3 / 5,5 / 8 / 15 m³/jam dengan head maks. 100 / 70 / 50 / 30 m</li>
<li>💧 <strong>2200 W</strong>: debit 3,5 / 6 / 9 / 17 / 21 / 25 m³/jam dengan head maks. 260 / 190 / 140 / 100 / 60 / 45 m</li>
<li>🔩 Pompa 4" (6" untuk model 6PSS 25 m³/jam), outlet 1,25" – 3"</li>
<li>🧰 Paket: pompa + kontroler hybrid. Panel surya, kabel, dan pipa dijual terpisah — hubungi kami untuk hitung jumlah panel sesuai model dan jam kerja</li>
</ul>
<p><em>Pilih varian sesuai kebutuhan: head (tinggi angkat total) dulu, baru debit. Semakin tinggi head, semakin kecil debitnya pada daya yang sama. Butuh bantuan menghitung? Konsultasi gratis.</em></p>
HTML,
            'specifications' => <<<HTML
<table><tbody>
<tr><th>Model</th><th>Daya</th><th>Debit Maks.</th><th>Head Maks.</th><th>Outlet</th><th>DC Input (Voc)</th></tr>
{$rows}<tr><th>AC Input</th><td colspan="5">90–240 V (hybrid, otomatis saat matahari kurang)</td></tr>
<tr><th>Tipe</th><td colspan="5">Submersible 4" (6PSS = 6"), kontroler hybrid termasuk</td></tr>
<tr><th>Merek</th><td colspan="5">LARENS</td></tr>
</tbody></table>
HTML,
            'price' => PjuTenagaSuryaSeeder::sellingPrice(self::COST[750]),
            'cost_price' => self::COST[750],
            'unit' => 'set',
            'weight_grams' => 18000, 'length_cm' => 110, 'width_cm' => 22, 'height_cm' => 22, // estimasi pompa 4" + kontroler
            'package_count' => 2,
            'requires_freight' => false,
            'warranty' => 'Garansi 1 tahun',
            'keywords' => 'pompa air tenaga surya, pompa submersible solar, pompa hybrid, larens, 4pss, 750w, 2200w, pompa sumur bor solar, pompa irigasi tenaga surya, kontroler pompa hybrid',
            'is_new' => true, 'is_featured' => true,
            'status' => 'published', 'published_at' => now(),
            'estimated_processing' => '3-7 hari kerja',
            'meta_title' => 'Pompa Air Tenaga Surya Hybrid LARENS 750W / 2200W + Kontroler — AC/DC Otomatis',
            'meta_description' => 'Pompa submersible tenaga surya hybrid LARENS + kontroler: jalan dari panel surya dan PLN/genset. 750 W (3–15 m³/jam) & 2200 W (3,5–25 m³/jam), head hingga 260 m.',
        ]);

        if (! $existing) {
            $product->categories()->sync(array_values(array_filter([$product->category_id, $parent?->id])));
            $this->attachImage($product, 'larens-4pss-tabel.jpg');
        }

        $created = 0;
        foreach (self::MODELS as $i => [$model, $w, $flow, $head, $outlet, $voc, $inch]) {
            $sku = 'LARENS-'.str_replace(['/', '.'], ['-', ''], $model);
            $variant = ProductVariant::firstOrCreate(['sku' => $sku], [
                'product_id' => $product->id,
                'name' => "{$w}W · {$flow} m³/jam · head {$head} m",
                'option_values' => ['Model' => "{$w}W · {$flow} m³/jam · head {$head} m"],
                'price' => PjuTenagaSuryaSeeder::sellingPrice(self::COST[$w]),
                'cost_price' => self::COST[$w],
                'weight_grams' => $w === 750 ? ($inch === 6 ? 24000 : 18000) : ($inch === 6 ? 34000 : 28000), // estimasi
                'length_cm' => $w === 750 ? 110 : 140, 'width_cm' => $inch === 6 ? 30 : 22, 'height_cm' => $inch === 6 ? 30 : 22,
                'is_active' => true, 'sort_order' => $i,
            ]);
            if ($variant->wasRecentlyCreated) {
                $created++;
                $current = (int) WarehouseStock::where('product_variant_id', $variant->id)->sum('quantity_available');
                if ($current < 2) {
                    $stock->adjust($product, $variant, 2 - $current, StockMovementType::Purchase, note: 'Stok awal (seeder)');
                }
            }
        }

        $this->command?->info('Pompa Hybrid LARENS: produk '.($existing ? 'sudah ada' : 'ditambahkan').", {$created} varian baru (750 W Rp 17.940.000 · 2200 W Rp 21.950.000; modal 13,45 / 16,458 jt).");
        $this->command?->warn('Berat/dimensi estimasi; stok awal 2/varian placeholder. Foto = tabel brosur — ganti foto pompa asli lewat Admin bila ada.');
    }

    private function attachImage(Product $product, string $file): void
    {
        $src = __DIR__.'/assets/pju/'.$file;
        if (! is_file($src)) {
            return;
        }
        $stored = 'products/pju/'.pathinfo($file, PATHINFO_FILENAME).'.jpg';
        if (! Storage::disk('public')->exists($stored)) {
            Storage::disk('public')->put($stored, (string) file_get_contents($src));
        }
        $watermark = app(WatermarkService::class);
        $final = $watermark->isSupported() ? ($watermark->apply($stored) ?? $stored) : $stored;
        $product->images()->create(['path' => $final, 'alt' => $product->name, 'sort_order' => 1, 'watermarked_at' => $watermark->isSupported() ? now() : null]);
        $product->forceFill(['main_image_path' => $final])->save();
    }
}
