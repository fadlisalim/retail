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
 * Katalog UBetter (stocklist gudang Indonesia Agustus 2026, harga Jakarta).
 *
 * Harga jual = harga 1 pcs, harga modal = harga tier 100 pcs ke atas — keduanya
 * diberikan langsung oleh pemilik. Stok awal = jumlah di stocklist supplier.
 * Spesifikasi hanya dari datasheet/brosur resmi (baterai 12,8V & 25,6V 100/200Ah,
 * inverter 11 kW, ESS lantai 15/16 kWh & 20 kWh); item tanpa datasheet hanya
 * memuat data stocklist dan ditandai "menyusul". Berat & dimensi bertanda
 * estimasi bila tidak tercantum di datasheet.
 *
 * Idempotent: produk/varian yang belum ada dibuat; yang sudah ada diperbarui harga,
 * modal, deskripsi, spesifikasi, berat/dimensi, foto & dokumen — KECUALI yang sudah
 * disunting/dihapus admin (SeedGuard): suntingan admin selalu menang, produk yang
 * dihapus admin tidak dihidupkan lagi. Stok hanya diisi saat dibuat. Datasheet pabrik dilampirkan versi bersih (datasheet-*.pdf):
 * kontak pabrik sudah ditutup & diganti kontak Energi.Click.
 */
class UbetterSeeder extends Seeder
{
    private const ASSETS = __DIR__.'/assets/ubetter';

    private int $created = 0;

    private int $updated = 0;

    private int $variantsCreated = 0;

    public function run(): void
    {
        $brand = Brand::firstOrCreate(['slug' => 'ubetter'], ['name' => 'UBetter', 'is_active' => true]);
        if (! $brand->logo_path && is_file(self::ASSETS.'/logo.png')) {
            Storage::disk('public')->put('brands/ubetter.png', (string) file_get_contents(self::ASSETS.'/logo.png'));
            $brand->forceFill(['logo_path' => 'brands/ubetter.png'])->save();
        }

        foreach ($this->catalog() as $row) {
            $this->seedProduct($brand, $row);
        }

        $this->command?->info("UBetter: {$this->created} produk baru, {$this->updated} produk diperbarui harganya, {$this->variantsCreated} varian baru.");
        $this->command?->warn('Inverter 3 kW & 6,2 kW belum ada foto & datasheet — unggah lewat Admin → Produk bila sudah ada.');
    }

    private function seedProduct(Brand $brand, array $row): void
    {
        $variants = $row['variants'] ?? null;
        $cheapest = $variants ? collect($variants)->sortBy('price')->first() : $row;
        $categories = Category::whereIn('slug', $row['categories'])->pluck('id', 'slug');

        $product = Product::withTrashed()->where('sku', $row['sku'])->orWhere('slug', $row['slug'])->first();
        $guard = new SeedGuard('ubetter:product:'.$row['sku']);
        if ($product?->trashed() || (! $product && $guard->exists())) {
            return; // dihapus admin — jangan dihidupkan lagi
        }

        if (! $product) {
            $product = Product::create([
                'sku' => $row['sku'],
                'slug' => $row['slug'],
                'name' => $row['name'],
                'category_id' => $categories[$row['categories'][0]] ?? $categories->first(),
                'brand_id' => $brand->id,
                'model' => $row['model'],
                'product_type' => $variants ? 'variable' : 'simple',
                'condition' => 'new',
                'short_description' => $row['short'],
                'description' => $this->description($row),
                'specifications' => $this->table($row['specs']),
                'price' => $cheapest['price'],
                'sale_price' => null,
                'cost_price' => $cheapest['cost'],
                'unit' => 'unit',
                'weight_grams' => $cheapest['weight'],
                'length_cm' => $cheapest['dims'][0], 'width_cm' => $cheapest['dims'][1], 'height_cm' => $cheapest['dims'][2],
                'package_count' => 1,
                'requires_freight' => $row['freight'] ?? false,
                'warranty' => $row['warranty'],
                'estimated_processing' => '1-3 hari kerja (stok gudang Jakarta)',
                'keywords' => $row['keywords'],
                'is_new' => true,
                'is_featured' => $row['featured'] ?? false,
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => Str::limit($row['name'], 60, '').' — '.brand(),
                'meta_description' => Str::limit(strip_tags($row['short']), 155),
            ]);
            $product->categories()->sync($categories->values()->all());
            $guard->apply($product, $this->productFields($row, $cheapest));
            $this->created++;
            if (! $variants) {
                $this->setStock($product, null, $row['stock']);
            }
        } else {
            // Stocklist + datasheet = acuan, kecuali kolom yang sudah disunting admin.
            $guard->apply($product, $this->productFields($row, $cheapest));
            $product->save();
            $current = $product->categories()->pluck('categories.id')->all();
            foreach ($categories as $id) {
                if ($guard->shouldAdd('categories', (string) $id, in_array($id, $current))) {
                    $product->categories()->attach($id);
                }
            }
            $this->updated++;
        }

        foreach ($variants ?? [] as $i => $v) {
            $variant = ProductVariant::where('sku', $v['sku'])->first();
            $variantGuard = new SeedGuard('ubetter:variant:'.$v['sku']);
            $variantFields = [
                'name' => $v['name'], 'option_values' => [$row['option'] => $v['name']],
                'price' => $v['price'], 'cost_price' => $v['cost'], 'weight_grams' => $v['weight'],
                'length_cm' => $v['dims'][0], 'width_cm' => $v['dims'][1], 'height_cm' => $v['dims'][2],
            ];
            if (! $variant && $variantGuard->exists()) {
                continue; // varian dihapus admin
            }
            if (! $variant) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $v['sku'],
                    'name' => $v['name'],
                    'option_values' => [$row['option'] => $v['name']],
                    'price' => $v['price'],
                    'sale_price' => null,
                    'cost_price' => $v['cost'],
                    'weight_grams' => $v['weight'],
                    'length_cm' => $v['dims'][0], 'width_cm' => $v['dims'][1], 'height_cm' => $v['dims'][2],
                    'is_active' => true,
                    'sort_order' => $i,
                ]);
                $this->variantsCreated++;
                $this->setStock($product, $variant, $v['stock']);
            }
            $variantGuard->apply($variant, $variantFields);
            $variant->save();
            $variantGuard->commit();
        }

        $this->syncImages($product, $guard, $row['images'] ?? [], $row['variant_images'] ?? []);
        foreach ($row['docs'] ?? [] as [$title, $file]) {
            $this->attachDocument($product, $guard, $title, $file);
        }
        $guard->commit();
    }

    /** Kolom produk yang mengikuti stocklist + datasheet. */
    private function productFields(array $row, array $cheapest): array
    {
        return [
            'price' => $cheapest['price'], 'cost_price' => $cheapest['cost'],
            'name' => $row['name'], 'model' => $row['model'], 'short_description' => $row['short'],
            'description' => $this->description($row), 'specifications' => $this->table($row['specs']),
            'weight_grams' => $cheapest['weight'],
            'length_cm' => $cheapest['dims'][0], 'width_cm' => $cheapest['dims'][1], 'height_cm' => $cheapest['dims'][2],
            'requires_freight' => $row['freight'] ?? false, 'warranty' => $row['warranty'],
        ];
    }

    // ------------------------------------------------------------------ katalog

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        return [
            $this->lfp12(), $this->lfp25(), $this->powerwall(), $this->rack(), $this->ess25(),
            $this->floor1516(), $this->vertical(), $this->stack20(),
            ...$this->inverters(),
            $this->allInOne(),
        ];
    }

    private function lfp12(): array
    {
        $common = ['Tegangan nominal' => '12,8 V', 'Kimia sel' => 'LiFePO4 (Lithium Iron Phosphate), sel Grade A', 'Tegangan cut-off discharge / charge' => '10,0 V / 14,6 V', 'Siklus hidup' => '≥ 4.000 siklus @ 80% DOD', 'Suhu operasi' => 'Charge 0–55 °C · discharge −20–60 °C', 'Suhu penyimpanan' => '−20–45 °C (kelembapan ≤ 75% RH)', 'Material casing' => 'ABS (tahan api)', 'Sertifikasi' => 'CE, RoHS, UN38.3, MSDS', 'Garansi' => '5 tahun'];

        return [
            'sku' => 'UB-LFP12', 'slug' => 'baterai-lifepo4-ubetter-12-8v-6-200ah', 'model' => 'UB-LFP12.8 series', 'option' => 'Kapasitas',
            'name' => 'Baterai Lithium LiFePO4 UBetter 12,8V (6Ah–200Ah)',
            'short' => 'Baterai LiFePO4 12,8V pengganti aki untuk PLTS off-grid, PJU, RV/kapal, CCTV & backup DC — sel Grade A, ≥ 4.000 siklus, garansi 5 tahun. Pilihan 6, 20, 30, 50, 100 dan 200Ah. Stok Jakarta.',
            'categories' => ['baterai-lithium-lifepo4', 'baterai'],
            'warranty' => '5 tahun', 'featured' => true,
            'keywords' => 'baterai lifepo4 12v, baterai lithium 12v 100ah, lifepo4 12.8v 200ah, pengganti aki, baterai plts, ubetter',
            'images' => [['lfp12-100.jpg', true, 'Baterai LiFePO4 UBetter 12,8V 100Ah — spesifikasi'], ['lfp12-200.jpg', true, 'Baterai LiFePO4 UBetter 12,8V 200Ah — spesifikasi']],
            'variant_images' => ['UB-LFP12.8-100' => 'lfp12-100.jpg', 'UB-LFP12.8-200' => 'lfp12-200.jpg'],
            'variants' => [
                ['sku' => 'UB-LFP12.8-6', 'name' => '12,8V 6Ah (77 Wh)', 'price' => 398_000, 'cost' => 292_000, 'stock' => 15, 'weight' => 1_000, 'dims' => [16, 8, 11], 'note' => 'estimasi'],
                ['sku' => 'UB-LFP12.8-20', 'name' => '12,8V 20Ah (256 Wh)', 'price' => 724_000, 'cost' => 530_000, 'stock' => 25, 'weight' => 2_800, 'dims' => [19, 9, 18], 'note' => 'estimasi'],
                ['sku' => 'UB-LFP12.8-30', 'name' => '12,8V 30Ah (384 Wh)', 'price' => 1_730_000, 'cost' => 1_270_000, 'stock' => 15, 'weight' => 4_000, 'dims' => [21, 14, 18], 'note' => 'estimasi'],
                ['sku' => 'UB-LFP12.8-50', 'name' => '12,8V 50Ah (640 Wh)', 'price' => 1_880_000, 'cost' => 1_480_000, 'stock' => 15, 'weight' => 6_000, 'dims' => [24, 15, 22], 'note' => 'estimasi'],
                ['sku' => 'UB-LFP12.8-100', 'name' => '12,8V 100Ah (1.280 Wh)', 'price' => 3_200_000, 'cost' => 2_500_000, 'stock' => 52, 'weight' => 11_000, 'dims' => [34, 18, 22], 'detail' => '330 × 172 × 215 mm · ±10,5 kg · charge 50 A (maks 100 A) · discharge 100 A (puncak 150 A, 10 dtk) · terminal M8'],
                ['sku' => 'UB-LFP12.8-200', 'name' => '12,8V 200Ah (2.560 Wh)', 'price' => 6_300_000, 'cost' => 4_900_000, 'stock' => 5, 'weight' => 21_500, 'dims' => [53, 25, 23], 'detail' => '522 × 240 × 218 mm · ±20,5 kg · charge 100 A · discharge 100 A (puncak 200 A, 10 dtk) · terminal M8'],
            ],
            'specs' => $common + ['Pilihan kapasitas' => '6 · 20 · 30 · 50 · 100 · 200 Ah', 'Detail 100Ah' => '1.280 Wh · 330 × 172 × 215 mm · ±10,5 kg · arus charge 50 A (maks 100 A) · discharge maks 100 A (puncak 150 A/10 dtk) · terminal M8', 'Detail 200Ah' => '2.560 Wh · 522 × 240 × 218 mm · ±20,5 kg · arus charge 100 A · discharge maks 100 A (puncak 200 A/10 dtk) · terminal M8', 'Detail 6–50Ah' => 'Datasheet menyusul — tanyakan arus maks & dimensi ke CS'],
            'intro' => '<p><strong>Baterai LiFePO4 UBetter 12,8V</strong> adalah pengganti langsung aki 12V (VRLA/basah) dengan umur jauh lebih panjang: ≥ 4.000 siklus pada 80% DOD, lebih ringan, tanpa perawatan, dan aman (kimia lithium iron phosphate tidak mudah terbakar). Cocok untuk PLTS off-grid kecil, PJU tenaga surya, RV/camper, kapal, CCTV, pompa DC, dan backup listrik rumah.</p>',
        ];
    }

    private function lfp25(): array
    {
        return [
            'sku' => 'UB-LFP25', 'slug' => 'baterai-lifepo4-ubetter-25-6v-50-200ah', 'model' => 'UB-LFP25.6 series', 'option' => 'Kapasitas',
            'name' => 'Baterai Lithium LiFePO4 UBetter 25,6V (50Ah–200Ah)',
            'short' => 'Baterai LiFePO4 24V (25,6V) untuk inverter 24V, PLTS off-grid/hybrid, golf cart, forklift & backup rumah — BMS dengan komunikasi RS485/RS232/CAN, ≥ 4.000 siklus, garansi 5 tahun. Pilihan 50, 100, 200Ah.',
            'categories' => ['baterai-lithium-lifepo4', 'baterai'],
            'warranty' => '5 tahun',
            'keywords' => 'baterai lifepo4 24v, baterai lithium 25.6v 100ah, lifepo4 24v 200ah, baterai inverter 24v, ubetter',
            'images' => [['lfp25-100.jpg', true, 'Baterai LiFePO4 UBetter 25,6V 100Ah — spesifikasi'], ['lfp25-200.jpg', true, 'Baterai LiFePO4 UBetter 25,6V 200Ah — spesifikasi']],
            'variant_images' => ['UB-LFP25.6-100' => 'lfp25-100.jpg', 'UB-LFP25.6-200' => 'lfp25-200.jpg'],
            'variants' => [
                ['sku' => 'UB-LFP25.6-50', 'name' => '25,6V 50Ah (1.280 Wh)', 'price' => 2_790_000, 'cost' => 2_050_000, 'stock' => 10, 'weight' => 11_000, 'dims' => [34, 18, 22], 'note' => 'estimasi'],
                ['sku' => 'UB-LFP25.6-100', 'name' => '25,6V 100Ah (2.560 Wh)', 'price' => 6_300_000, 'cost' => 4_900_000, 'stock' => 5, 'weight' => 21_500, 'dims' => [34, 18, 22], 'detail' => '330 × 172 × 215 mm · ±20,5 kg · 8S1P · charge 50 A (maks 100 A) · discharge 100 A (puncak 200 A, 10 dtk)'],
                ['sku' => 'UB-LFP25.6-200', 'name' => '25,6V 200Ah (5.120 Wh)', 'price' => 10_050_000, 'cost' => 7_900_000, 'stock' => 5, 'weight' => 42_500, 'dims' => [53, 25, 23], 'detail' => '522 × 240 × 218 mm · ±41 kg · 8S2P · charge 100 A · discharge 200 A (puncak 200 A, 10 dtk)'],
            ],
            'specs' => ['Tegangan nominal' => '25,6 V (sistem 24V)', 'Kimia sel' => 'LiFePO4, sel Grade A', 'Tegangan cut-off discharge / charge' => '20,0 V / 29,2 V', 'Komunikasi BMS' => 'RS485, RS232, CAN (100Ah & 200Ah)', 'Siklus hidup' => '≥ 4.000 siklus @ 80% DOD', 'Suhu operasi' => 'Charge 0–55 °C · discharge −20–60 °C', 'Suhu penyimpanan' => '−20–45 °C (≤ 75% RH)', 'Terminal' => 'M8', 'Material casing' => 'ABS (tahan api)', 'Sertifikasi' => 'CE, RoHS, UN38.3, MSDS', 'Detail 100Ah' => '2.560 Wh · 8S1P · 330 × 172 × 215 mm · ±20,5 kg · charge 50 A (maks 100 A) · discharge maks 100 A (puncak 200 A/10 dtk)', 'Detail 200Ah' => '5.120 Wh · 8S2P · 522 × 240 × 218 mm · ±41 kg · charge 100 A · discharge maks 200 A (puncak 200 A/10 dtk)', 'Detail 50Ah' => 'Datasheet menyusul', 'Garansi' => '5 tahun'],
            'intro' => '<p><strong>Baterai LiFePO4 UBetter 25,6V</strong> untuk sistem 24V: inverter off-grid/hybrid 24V, PLTS rumah kecil, golf cart, forklift listrik, CCTV & peralatan medis. Varian 100Ah & 200Ah dilengkapi komunikasi BMS <strong>RS485 / RS232 / CAN</strong> untuk inverter yang mendukung.</p>',
        ];
    }

    private function powerwall(): array
    {
        return [
            'sku' => 'UB-PW51', 'slug' => 'baterai-powerwall-ubetter-51-2v-5-10kwh-wall-mounted', 'model' => 'UBetter Powerwall 51,2V', 'option' => 'Kapasitas',
            'name' => 'Baterai Powerwall UBetter 51,2V 5 kWh / 10 kWh (Wall-Mounted)',
            'short' => 'Baterai rumah LiFePO4 48V tempel dinding untuk inverter hybrid/off-grid 48V — 51,2V 100Ah (5 kWh) atau 200Ah (10 kWh), sel Grade A, garansi 10 tahun. Stok Jakarta.',
            'categories' => ['baterai-wall-mounted', 'baterai-lithium-lifepo4', 'baterai'],
            'warranty' => '10 tahun', 'featured' => true, 'freight' => true,
            'keywords' => 'powerwall 48v, baterai 10kwh, baterai 5kwh, lifepo4 51.2v 200ah, baterai wall mounted, baterai plts rumah, ubetter',
            'images' => [['powerwall-10kwh.jpg', false, 'Baterai Powerwall UBetter 10 kWh'], ['powerwall-5kwh.jpg', false, 'Baterai Powerwall UBetter 5 kWh'], ['powerwall.jpg', false, 'Baterai Powerwall UBetter 51,2V wall-mounted']],
            'docs' => [['Datasheet Powerwall UBetter 5 kWh (PDF)', 'datasheet-powerwall-5kwh.pdf'], ['Datasheet Powerwall UBetter 10 kWh (PDF)', 'datasheet-powerwall-10kwh.pdf']],
            'variant_images' => ['UB-PW51-100' => 'powerwall-5kwh.jpg', 'UB-PW51-200' => 'powerwall-10kwh.jpg'],
            'variants' => [
                ['sku' => 'UB-PW51-100', 'name' => '51,2V 100Ah (5,12 kWh)', 'price' => 19_500_000, 'cost' => 11_900_000, 'stock' => 100, 'weight' => 50_000, 'dims' => [66, 44, 20], 'detail' => '16S1P · 600 × 380 × 145 mm · ±45 kg · charge/discharge maks 100 A'],
                ['sku' => 'UB-PW51-200', 'name' => '51,2V 200Ah (10,24 kWh)', 'price' => 29_700_000, 'cost' => 20_600_000, 'stock' => 100, 'weight' => 97_000, 'dims' => [75, 64, 22], 'detail' => '16S2P · 690 × 580 × 150 mm · ±90 kg · charge/discharge maks 200 A'],
            ],
            'specs' => [
                'Tegangan nominal / kerja' => '51,2 V (sistem 48V)',
                '5 kWh' => '100Ah · 5.120 Wh · 16S1P · 600 × 380 × 145 mm · ±45 kg · arus charge & discharge maks 100 A',
                '10 kWh' => '200Ah · 10.240 Wh · 16S2P · 690 × 580 × 150 mm · ±90 kg · arus charge & discharge maks 200 A',
                'Charge standar' => '40 A (±5 jam)',
                'Tegangan charge / cut-off discharge' => '58,4 V / 40 V',
                'Suhu operasi' => 'Charge 0–55 °C · discharge −20–65 °C (kelembapan 60 ± 25% RH)',
                'Casing' => 'Pelat baja cold-rolled',
                'Fitur' => 'Tempel dinding atau berdiri di lantai · bisa diparalel (plug & play) · layar HD · pendinginan alami',
                'Kimia sel' => 'LiFePO4, sel Grade A (umur hingga 10 tahun)',
                'Garansi' => '10 tahun',
            ],
            'intro' => '<p><strong>Powerwall UBetter 51,2V</strong> — baterai penyimpanan energi rumah untuk inverter hybrid / off-grid 48V. Bisa ditempel di dinding atau berdiri di lantai, bisa diparalel untuk menambah kapasitas, dan dilengkapi layar untuk memantau kondisi baterai. Simpan energi surya siang hari untuk dipakai malam atau saat PLN padam.</p>',
        ];
    }

    private function rack(): array
    {
        return [
            'sku' => 'UB-RK51', 'slug' => 'baterai-rack-ubetter-51-2v-5-10kwh-server-rack', 'model' => 'UBetter Rack 51,2V', 'option' => 'Kapasitas',
            'name' => 'Baterai Rack UBetter 51,2V 5 kWh / 10 kWh (Server Rack, Bisa Paralel)',
            'short' => 'Modul baterai LiFePO4 48V tipe rack (bisa ditumpuk/paralel) untuk ESS rumah, kantor & BTS — 51,2V 100Ah (5 kWh) atau 200Ah (10 kWh), sel Grade A, garansi 10 tahun.',
            'categories' => ['baterai-rack-mounted', 'baterai-lithium-lifepo4', 'baterai'],
            'warranty' => '10 tahun', 'freight' => true,
            'keywords' => 'baterai rack 48v, baterai server rack, lifepo4 51.2v 100ah, baterai 5kwh rack, ess 48v, ubetter',
            'images' => [['rack-3u.jpg', false, 'Baterai rack UBetter 51,2V 3U (modul bertumpuk)'], ['rack.jpg', false, 'Baterai rack UBetter 51,2V']],
            'docs' => [['Datasheet Baterai Rack UBetter 5 kWh 3U (PDF)', 'datasheet-rack-3u.pdf']],
            'variant_images' => ['UB-RK51-100' => 'rack-3u.jpg'],
            'variants' => [
                ['sku' => 'UB-RK51-100', 'name' => '51,2V 100Ah (5,12 kWh, 3U)', 'price' => 19_500_000, 'cost' => 11_900_000, 'stock' => 100, 'weight' => 55_000, 'dims' => [50, 56, 20], 'detail' => 'UBT-D05-U01 · 16S1P · 420 × 482 × 133 mm (3U) · ±50 kg · charge/discharge maks 100 A'],
                ['sku' => 'UB-RK51-200', 'name' => '51,2V 200Ah (10,24 kWh)', 'price' => 29_700_000, 'cost' => 20_600_000, 'stock' => 50, 'weight' => 90_000, 'dims' => [62, 50, 22], 'note' => 'estimasi'],
            ],
            'specs' => [
                'Tegangan nominal' => '51,2 V (sistem 48V)',
                '5 kWh (UBT-D05-U01)' => '100Ah · 5.120 Wh · 16S1P · 420 × 482 × 133 mm (3U, rack 19") · ±50 kg · arus charge/discharge maks 100 A',
                '10 kWh' => '200Ah · 10.240 Wh — datasheet menyusul',
                'Tegangan charge / cut-off discharge' => '58,4 V / 40 V',
                'Komunikasi BMS' => 'RS485, RS232, CAN',
                'Siklus hidup' => '> 8.000 siklus @ 80% DOD, 25 °C · umur desain 10+ tahun',
                'Suhu kerja' => '0–55 °C',
                'Fitur' => 'Desain rack standar plug & play · perawatan dari depan · modul hot-swappable · BMS canggih',
                'Sertifikasi' => 'CE, UN38.3, MSDS',
                'Garansi' => '10 tahun',
            ],
            'intro' => '<p><strong>Baterai Rack UBetter 51,2V</strong> — modul baterai 48V format rack 19" (3U) yang bisa ditumpuk dan diparalel untuk menambah kapasitas: rumah, kantor, toko, hingga telekomunikasi. Komunikasi BMS RS485/RS232/CAN untuk inverter hybrid yang mendukung, &gt; 8.000 siklus.</p>',
        ];
    }

    private function ess25(): array
    {
        return [
            'sku' => 'UB-ESS25-100', 'slug' => 'baterai-ess-ubetter-25-6v-100ah-2-5kwh', 'model' => 'UBetter ESS 25,6V 100Ah',
            'name' => 'Baterai ESS Rumah UBetter 25,6V 100Ah (2,56 kWh) Garansi 10 Tahun',
            'short' => 'Baterai penyimpanan energi rumah 24V 2,56 kWh dengan layar indikator — LiFePO4 sel Grade A, garansi 10 tahun. Untuk inverter 24V & PLTS rumah kecil.',
            'categories' => ['baterai-lithium-lifepo4', 'baterai'],
            'warranty' => '10 tahun',
            'price' => 10_400_000, 'cost' => 5_600_000, 'stock' => 35, 'weight' => 30_000, 'dims' => [52, 32, 42], 'note' => 'estimasi',
            'keywords' => 'baterai ess 24v, baterai rumah 2.5kwh, lifepo4 25.6v 100ah, baterai garansi 10 tahun, ubetter',
            'images' => [['ess-25v.jpg', false, 'Baterai ESS UBetter 25,6V 100Ah']],
            'specs' => ['Tegangan nominal' => '25,6 V (sistem 24V)', 'Kapasitas' => '100Ah (2,56 kWh)', 'Kimia sel' => 'LiFePO4, sel Grade A', 'Garansi' => '10 tahun', 'Arus, komunikasi, dimensi & berat' => 'Datasheet menyusul'],
            'intro' => '<p><strong>Baterai ESS UBetter 25,6V 100Ah</strong> — unit penyimpanan energi rumah 24V dengan layar indikator, garansi 10 tahun. Berbeda dengan baterai blok 25,6V (garansi 5 tahun), unit ini dirancang sebagai baterai rumah.</p>',
        ];
    }

    private function floor1516(): array
    {
        return [
            'sku' => 'UB-FS314', 'slug' => 'baterai-floor-standing-ubetter-15-16kwh-314ah', 'model' => 'UBetter Vertical type 15kWh/16kWh', 'option' => 'Kapasitas',
            'name' => 'Baterai Floor-Standing UBetter 15 kWh / 16 kWh (314Ah, Beroda)',
            'short' => 'Baterai rumah berdiri (beroda) kapasitas besar: 48V 314Ah 15,07 kWh atau 51,2V 314Ah 16,07 kWh, arus kontinu 200A, bisa diparalel, layar HD, sel Grade A, garansi 10 tahun.',
            'categories' => ['baterai-lithium-lifepo4', 'baterai'],
            'warranty' => '10 tahun', 'freight' => true, 'featured' => true,
            'keywords' => 'baterai 15kwh, baterai 16kwh, lifepo4 314ah, baterai rumah 48v, home energy storage, ess floor standing, ubetter',
            'images' => [['floor-15-16kwh.jpg', false, 'Baterai floor-standing UBetter 15/16 kWh'], ['floor-15-16kwh-2.jpg', false, 'Baterai floor-standing UBetter tampak samping']],
            'docs' => [['Datasheet Baterai Floor-Standing UBetter 15/16 kWh (PDF)', 'datasheet-floor-15-16kwh.pdf']],
            'variants' => [
                ['sku' => 'UB-FS48-314', 'name' => '48V 314Ah (15,07 kWh, 15S1P)', 'price' => 32_500_000, 'cost' => 23_800_000, 'stock' => 100, 'weight' => 130_000, 'dims' => [86, 50, 32], 'detail' => '±120 kg · 800 × 440 × 260 mm'],
                ['sku' => 'UB-FS51-314', 'name' => '51,2V 314Ah (16,07 kWh, 16S1P)', 'price' => 34_500_000, 'cost' => 25_400_000, 'stock' => 100, 'weight' => 136_000, 'dims' => [86, 50, 32], 'detail' => '±126 kg · 800 × 440 × 260 mm'],
            ],
            'specs' => ['Konfigurasi' => '15S1P (48V) · 16S1P (51,2V)', 'Kapasitas' => '314Ah — 15,07 kWh (48V) · 16,07 kWh (51,2V)', 'Tegangan akhir discharge' => '37,5 V (48V) · 40 V (51,2V)', 'Tegangan charge (cut-off)' => '54,75 V (48V) · 58,4 V (51,2V)', 'Impedansi internal' => '≤ 50 mΩ', 'Charge standar' => '100 A (± 2,5 jam)', 'Arus charge / discharge kontinu maks' => '200 A / 200 A', 'Suhu operasi' => 'Charge 0–55 °C · discharge −20–65 °C', 'Dimensi' => '800 × 440 × 260 mm', 'Berat' => '±120 kg (15 kWh) · ±126 kg (16 kWh)', 'Fitur' => 'Bisa paralel (plug & play), layar HD, pendinginan alami, desain vertikal beroda, sel Grade A (umur hingga 10 tahun)', 'Garansi' => '10 tahun'],
            'intro' => '<p><strong>UBetter Vertical 15 kWh / 16 kWh</strong> — baterai rumah berdiri dengan roda, kapasitas besar untuk menyimpan energi surya seharian atau backup penuh saat PLN padam. Bisa diparalel untuk kapasitas lebih besar, layar HD untuk memantau kondisi baterai.</p>',
        ];
    }

    private function vertical(): array
    {
        return [
            'sku' => 'UB-VT48', 'slug' => 'baterai-ubetter-vertical-touchscreen-20-30kwh', 'model' => 'UBetter Vertical Touchscreen', 'option' => 'Kapasitas',
            'name' => 'Baterai UBetter Vertical Touchscreen 48V 20 kWh / 30 kWh',
            'short' => 'Baterai rumah/usaha 48V kapasitas jumbo dengan layar sentuh, Bluetooth & WiFi, proteksi api aerosol, active balancing 3A: 410Ah (19,68 kWh) atau 628Ah (±30 kWh). Garansi 10 tahun.',
            'categories' => ['baterai-lithium-lifepo4', 'baterai'],
            'warranty' => '10 tahun', 'freight' => true,
            'keywords' => 'baterai 20kwh, baterai 30kwh, lifepo4 48v 410ah, baterai touchscreen, ess 20kwh, ubetter',
            'images' => [['vertical-20kwh.jpg', false, 'Baterai UBetter Vertical Touchscreen 20 kWh'], ['vertical-30kwh.jpg', false, 'Baterai UBetter Vertical Touchscreen']],
            'docs' => [['Datasheet Baterai UBetter Vertical Touchscreen 20 kWh (PDF)', 'datasheet-vertical-20kwh.pdf']],
            'variants' => [
                ['sku' => 'UB-VT48-410', 'name' => '48V 410Ah (19,68 kWh)', 'price' => 48_200_000, 'cost' => 39_300_000, 'stock' => 20, 'weight' => 182_000, 'dims' => [80, 36, 78], 'detail' => '15S2P · ±162 kg (bruto ±182 kg) · 750 × 304 × 710 mm'],
                ['sku' => 'UB-VT48-628', 'name' => '48V 628Ah (±30 kWh)', 'price' => 62_400_000, 'cost' => 49_990_000, 'stock' => 12, 'weight' => 260_000, 'dims' => [85, 40, 100], 'note' => 'estimasi'],
            ],
            'specs' => ['Tegangan nominal' => '48 V', 'Kapasitas' => '410Ah — 19,68 kWh (15S2P) · 628Ah — ±30 kWh (detail menyusul)', 'Arus charge / discharge kontinu maks (20 kWh)' => '200 A / 200 A', 'Proteksi overcharge / cut-off discharge / over-discharge (20 kWh)' => '55 V / 40 V / 38 V', 'Suhu' => 'Charge 0–55 °C · discharge −10–60 °C · simpan −20–45 °C', 'Kelembapan operasi' => '10–90% RH tanpa kondensasi', 'Dimensi & berat (20 kWh)' => '750 × 304 × 710 mm · ±162 kg (bruto ±182 kg)', 'Proteksi' => 'IP20 · proteksi api aerosol · active balancing 3 A', 'Konektivitas' => 'Layar sentuh, Bluetooth & WiFi', 'Garansi' => '10 tahun'],
            'intro' => '<p><strong>UBetter Vertical Touchscreen</strong> — baterai LiFePO4 48V berkapasitas sangat besar untuk rumah besar, ruko, kantor dan usaha. Layar sentuh vertikal, pemantauan via Bluetooth/WiFi, proteksi api aerosol bawaan, dan active balancing 3A agar sel awet.</p>',
        ];
    }

    private function stack20(): array
    {
        return [
            'sku' => 'UB-STK20-12K', 'slug' => 'ess-stack-ubetter-20kwh-inverter-12kw', 'model' => 'UBetter Stack ESS 20 kWh + 12 kW',
            'name' => 'ESS Stack UBetter 20 kWh + Inverter 12 kW (Modular Bertumpuk)',
            'short' => 'Sistem penyimpanan energi modular bertumpuk: baterai LiFePO4 total 20 kWh + inverter 12 kW dalam satu menara beroda. Sel Grade A, garansi baterai 10 tahun. Stok terbatas.',
            'categories' => ['baterai-all-in-one-ess', 'baterai'],
            'warranty' => 'Baterai 10 tahun',
            'price' => 78_440_000, 'cost' => 65_700_000, 'stock' => 1, 'weight' => 230_000, 'dims' => [75, 45, 160], 'note' => 'estimasi', 'freight' => true,
            'keywords' => 'ess 20kwh, inverter 12kw, baterai stack, energy storage modular, ubetter',
            'images' => [['stack-20kwh.jpg', false, 'ESS Stack UBetter 20 kWh + inverter 12 kW']],
            'specs' => ['Baterai' => 'LiFePO4 sel Grade A, total 20 kWh (modul bertumpuk)', 'Inverter' => '12 kW terintegrasi di atas tumpukan', 'Garansi' => 'Baterai 10 tahun', 'Tegangan, arus, dimensi & berat' => 'Datasheet menyusul'],
            'intro' => '<p><strong>ESS Stack UBetter 20 kWh + 12 kW</strong> — inverter dan modul baterai dalam satu menara bertumpuk beroda, rapi dan mudah dipindahkan. Cocok untuk rumah besar dan usaha yang butuh backup penuh.</p>',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function inverters(): array
    {
        $soon = 'Datasheet menyusul — tanyakan spesifikasi lengkap (tegangan baterai, MPPT, input PV) ke CS';
        $base = fn (string $kw, string $sku, int $price, int $cost, int $stock, string $warranty, int $weight, array $dims) => [
            'sku' => $sku, 'slug' => 'inverter-surya-ubetter-'.str_replace(',', '-', $kw).'-kw', 'model' => 'UBetter '.$kw.' kW',
            'name' => 'Inverter Surya UBetter '.$kw.' kW',
            'short' => 'Inverter tenaga surya UBetter '.$kw.' kW untuk PLTS rumah & usaha, garansi '.$warranty.'. Stok gudang Jakarta — spesifikasi detail via CS.',
            'categories' => ['inverter-off-grid', 'inverter'],
            'warranty' => $warranty,
            'price' => $price, 'cost' => $cost, 'stock' => $stock, 'weight' => $weight, 'dims' => $dims, 'note' => 'estimasi',
            'keywords' => 'inverter '.$kw.'kw, inverter surya, inverter plts, inverter off grid, ubetter',
            'specs' => ['Daya' => $kw.' kW', 'Garansi' => $warranty, 'Spesifikasi teknis' => $soon],
            'intro' => '<p><strong>Inverter Surya UBetter '.$kw.' kW</strong> — inverter untuk sistem PLTS rumah dan usaha. Spesifikasi lengkap (tegangan baterai, rentang MPPT, input PV) belum kami terima dari pabrikan; hubungi CS untuk memastikan kecocokan dengan baterai & panel Anda.</p>',
        ];

        $gpeo = fn (string $kw, string $model, string $sku, int $price, int $cost, int $stock, int $weight, array $dims, array $s) => [
            'sku' => $sku, 'slug' => 'inverter-surya-ubetter-'.$kw.'-kw', 'model' => $model,
            'name' => 'Inverter Hybrid Off-Grid UBetter '.$kw.' kW '.$s['vbat'].' ('.$model.', WiFi)',
            'short' => 'Inverter hybrid off-grid '.$kw.' kW sistem '.$s['vbat'].': PV maks '.$s['pv'].', MPPT 85–450 V, charge/discharge baterai '.$s['bat_current'].', pure sine wave, IP54, '.$s['parallel'].', WiFi & RS485 BMS. Garansi 4 tahun.',
            'categories' => ['inverter-off-grid', 'inverter-hybrid', 'inverter'],
            'warranty' => '4 tahun',
            'price' => $price, 'cost' => $cost, 'stock' => $stock, 'weight' => $weight, 'dims' => $dims,
            'keywords' => 'inverter '.$kw.'kw, inverter hybrid '.$kw.'kw, inverter off grid '.$kw.'kw, '.strtolower($model).', inverter '.$s['vbat'].', ubetter',
            'images' => [['inverter-gpeo.jpg', false, 'Inverter UBetter '.$model]],
            'docs' => [['Datasheet Inverter UBetter GPEO-4KL1 / 6KL1 / 12KL1 (PDF)', 'datasheet-inverter-4-6-12kw.pdf']],
            'specs' => [
                'Model' => $model,
                'Input PV' => 'Daya string maks '.$s['pv'].' · tegangan maks 500 V · MPPT 85–450 V · '.$s['mppt'],
                'Input PLN' => 'Maks '.$s['grid'].' · 220/230/240 Vac · 50/60 Hz · 170–280 Vac (UPS) / 90–280 Vac (beban)',
                'Baterai' => 'LiFePO4 / aki · nominal '.$s['vbat'].' ('.$s['vrange'].') · charge/discharge maks '.$s['bat_current'].' · BMS RS485',
                'Output off-grid' => $s['out'].' · 220/230/240 Vac 50/60 Hz · puncak '.$s['peak'].' (5 dtk) · THD < 3%',
                'Paralel' => $s['parallel'],
                'Waktu transfer' => '10 ms (UPS) · 20 ms (beban)',
                'Efisiensi' => 'Puncak 98% · MPPT 99,9%',
                'Proteksi' => 'Hubung singkat & overvoltage output · surge Type III (PV & AC) · IP54',
                'Komunikasi' => 'RS232 / RS485 / dry contact / WiFi · layar LCD · update OTA',
                'Lingkungan' => '−10–55 °C · 5–95% RH · derating > 2.000 m · standby < '.$s['standby'],
                'Dimensi & berat' => $s['size'],
                'Garansi' => '4 tahun (penawaran distributor; datasheet pabrik 3 tahun)',
            ],
            'intro' => '<p><strong>Inverter Hybrid Off-Grid UBetter '.$kw.' kW ('.$model.')</strong> — inverter '.$s['vbat'].' untuk PLTS rumah & usaha: MPPT lebar 85–450 V, efisiensi MPPT 99,9%, bisa jalan dengan baterai lithium maupun aki, input genset/PLN, IP54 dan pemantauan WiFi. '.$s['intro_extra'].'</p>',
        ];

        $inv11 = [
            'sku' => 'UB-INV-11K', 'slug' => 'inverter-hybrid-ubetter-11kw-48v-dual-mppt', 'model' => 'G*11048SMH',
            'name' => 'Inverter Hybrid Off-Grid UBetter 11 kW 48V (Dual MPPT 11.000 W, WiFi)',
            'short' => 'Inverter hybrid off-grid 11 kW 48V: dual MPPT 2 × 5.500 W (PV maks 500 VDC), charger 150A, pure sine wave, daya puncak 22 kVA, komunikasi BMS lithium RS232 + WiFi. Garansi 2 tahun.',
            'categories' => ['inverter-off-grid', 'inverter-hybrid', 'inverter'],
            'warranty' => '2 tahun', 'featured' => true,
            'price' => 16_300_000, 'cost' => 10_900_000, 'stock' => 5, 'weight' => 20_000, 'dims' => [62, 45, 22], 'note' => 'estimasi',
            'keywords' => 'inverter 11kw, inverter hybrid 11kw, inverter 48v, dual mppt, inverter off grid 11kw, ubetter',
            'images' => [['inverter-11kw.jpg', false, 'Inverter Hybrid UBetter 11 kW']],
            'docs' => [['Katalog & spesifikasi Inverter UBetter 11 kW (PDF)', 'katalog-inverter-11kw.pdf']],
            'specs' => [
                'Model' => 'G*11048SMH',
                'Input PV' => '2 × 5.500 W (11.000 W) · Dual MPPT · maks 500 VDC · tracking 90–500 VDC · Vmp optimal 300–400 VDC · arus input 18 A / 18 A',
                'Baterai' => '48 VDC · charge 56,4 V · float 54 V · arus charge maks 150 A (AC maks 150 A) · Lithium (LiFePO4)',
                'Input AC' => 'L + N + PE · 220/230/240 VAC · mode APP 90–280 VAC · mode UPS 170–280 VAC · 50/60 Hz',
                'Output AC' => '11.000 W · puncak 22.000 VA · 220/230/240 VAC ±5% · pure sine wave',
                'Overload' => '21 dtk @105–150% · 11 dtk @150–200% · 400 ms @>200%',
                'Waktu transfer' => '10 ms (UPS) · 20 ms (APP)',
                'Komunikasi' => 'Kartu BMS lithium · RS232 (2400 baud) · WiFi card · dry contact',
                'Display' => 'LCD (mode operasi, beban, input, dll.)',
                'Lingkungan' => 'Operasi −10–50 °C · simpan −15–45 °C · 20–95% RH · ≤ 50 dB',
                'Sertifikasi' => 'CE',
                'Garansi' => '2 tahun',
            ],
            'intro' => '<p><strong>Inverter Hybrid Off-Grid UBetter 11 kW</strong> — inverter 48V berdaya besar untuk rumah besar, ruko dan usaha: dua MPPT independen (2 × 5.500 W) untuk dua arah atap, charger baterai 150A, transfer 10 ms mode UPS, serta komunikasi BMS lithium RS232 dan WiFi untuk pemantauan. Tahan iklim tropis (−10 hingga 50 °C).</p>',
        ];

        return [
            $base('3', 'UB-INV-3K', 9_900_000, 4_700_000, 30, '2 tahun', 10_000, [45, 35, 18]),
            $gpeo('6', 'GPEO-6KL1', 'UB-INV-6K', 13_800_000, 8_900_000, 2, 14_600, [56, 47, 24], [
                'vbat' => '48V', 'vrange' => '40–60 Vdc', 'pv' => '6.000 W', 'mppt' => '1 MPPT (2 string) · arus 27 A/channel · Isc 35 A',
                'grid' => '11.500 W / 50 A', 'bat_current' => '120 A / 130 A', 'out' => '6.000 W / 6.000 VA (27,3 A)', 'peak' => '12.000 VA',
                'parallel' => 'bisa paralel hingga 12 unit', 'standby' => '10 W', 'size' => '347 × 445 × 120 mm · ±12,4 kg (bruto ±14,6 kg)',
                'intro_extra' => 'Bisa diparalel hingga 12 unit untuk menambah daya.',
            ]),
            $base('6,2', 'UB-INV-6K2', 10_500_000, 5_500_000, 9, '2 tahun', 14_000, [52, 40, 20]),
            $inv11,
            $gpeo('12', 'GPEO-12KL1', 'UB-INV-12K', 19_800_000, 13_900_000, 12, 32_000, [75, 65, 24], [
                'vbat' => '48V', 'vrange' => '40–60 Vdc', 'pv' => '12.000 W', 'mppt' => '2 MPPT (2 string/MPPT) · arus 27/27 A · Isc 35/35 A',
                'grid' => '16.000 W / 90 A', 'bat_current' => '210 A / 230 A', 'out' => '12.000 W / 12.000 VA (54,5 A)', 'peak' => '24.000 VA',
                'parallel' => 'paralel hingga 6 unit (1 fasa) / 12 unit (3 fasa)', 'standby' => '20 W', 'size' => '525 × 630 × 125 mm · ±28,5 kg (bruto ±32 kg)',
                'intro_extra' => 'Dua MPPT untuk dua arah atap, dan bisa diparalel hingga 6 unit (1 fasa) atau 12 unit (3 fasa).',
            ]),
        ];
    }

    private function allInOne(): array
    {
        return [
            'sku' => 'UB-AIO', 'slug' => 'ess-all-in-one-ubetter-baterai-inverter', 'model' => 'UBetter All-in-One ESS', 'option' => 'Konfigurasi',
            'name' => 'ESS All-in-One UBetter (Baterai + Inverter Terintegrasi) 5–15 kWh',
            'short' => 'Baterai LiFePO4 dan inverter dalam satu kabinet beroda — tinggal sambung panel surya & listrik rumah. Pilihan 5 kWh + 5 kW, 8 kWh + 3 kW, 15 kWh + 5 kW, 15 kWh + 11 kW. Garansi baterai 10 th, inverter 2 th.',
            'categories' => ['baterai-all-in-one-ess', 'baterai'],
            'warranty' => 'Baterai 10 tahun · inverter 2 tahun', 'freight' => true,
            'keywords' => 'all in one ess, baterai inverter jadi satu, ess 15kwh, ess 5kwh, home energy storage, ubetter',
            'images' => [['all-in-one-vertical.jpg', false, 'ESS All-in-One UBetter (inverter + baterai vertikal)'], ['all-in-one.jpg', false, 'ESS All-in-One UBetter']],
            'docs' => [
                ['Brosur ESS All-in-One UBetter 5 kWh / 15 kWh (PDF)', 'datasheet-aio-vertical.pdf'],
                ['Spesifikasi All-in-One 5 kWh + 5 kW (PDF)', 'datasheet-aio-5k-5kwh.pdf'],
                ['Spesifikasi All-in-One 8 kWh + 3 kW (PDF)', 'datasheet-aio-3k-8kwh.pdf'],
                ['Spesifikasi All-in-One 15 kWh + 5 kW (PDF)', 'datasheet-aio-5k-15kwh.pdf'],
                ['Spesifikasi All-in-One 15 kWh + 11 kW (PDF)', 'datasheet-aio-11k-15kwh.pdf'],
            ],
            'variants' => [
                ['sku' => 'UB-AIO-5-5K', 'name' => 'Baterai 5 kWh + inverter 5 kW', 'price' => 28_700_000, 'cost' => 18_500_000, 'stock' => 2, 'weight' => 63_000, 'dims' => [82, 55, 22], 'detail' => 'UBT-N2HT5-P5 · 51,2V 100Ah (5,12 kWh) · 750 × 480 × 151 mm · ±57 kg · charge/discharge maks 100 A'],
                ['sku' => 'UB-AIO-8-3K', 'name' => 'Baterai 8 kWh + inverter 3 kW', 'price' => 29_700_000, 'cost' => 21_500_000, 'stock' => 8, 'weight' => 76_000, 'dims' => [90, 52, 30], 'detail' => '25,6V 314Ah (8,04 kWh, 8S1P) · 820 × 450 × 230,5 mm · ±70 kg · charge/discharge maks 150 A (± 2 jam)'],
                ['sku' => 'UB-AIO-15-5K', 'name' => 'Baterai 15 kWh + inverter 5 kW', 'price' => 39_700_000, 'cost' => 31_700_000, 'stock' => 7, 'weight' => 130_000, 'dims' => [95, 65, 30], 'detail' => 'UBT-N2HT5-P15 · 48V 314Ah (15,07 kWh) · 880 × 580 × 234 mm · ±120 kg · charge/discharge maks 100 A'],
                ['sku' => 'UB-AIO-15-11K', 'name' => 'Baterai 15 kWh + inverter 11 kW', 'price' => 49_800_000, 'cost' => 38_900_000, 'stock' => 8, 'weight' => 145_000, 'dims' => [110, 65, 30], 'detail' => 'UBT-N2HT11-P15 · 48V 314Ah (15,07 kWh) · 1030 × 580 × 234 mm · ±135 kg · charge 157 A / discharge 250 A'],
            ],
            'specs' => [
                'Isi' => 'Baterai LiFePO4 sel Grade A + inverter dua arah terintegrasi dalam satu kabinet vertikal',
                '5 kW + 5 kWh (UBT-N2HT5-P5)' => 'Baterai 51,2V 100Ah (5,12 kWh) · 750 × 480 × 151 mm · ±57 kg · charge/discharge maks 100 A',
                '5 kW + 15 kWh (UBT-N2HT5-P15)' => 'Baterai 48V 314Ah (15,07 kWh) · 880 × 580 × 234 mm · ±120 kg · charge/discharge maks 100 A',
                '11 kW + 15 kWh (UBT-N2HT11-P15)' => 'Baterai 48V 314Ah (15,07 kWh) · 1030 × 580 × 234 mm · ±135 kg · charge 157 A / discharge 250 A',
                '3 kW + 8 kWh' => 'Baterai 25,6V 314Ah (8,04 kWh, 8S1P) · 820 × 450 × 230,5 mm · ±70 kg · charge/discharge maks 150 A (charge ± 2 jam) · RS232 · 6.000 siklus',
                'Siklus baterai' => '5 kWh: 5.000 siklus (≥ 80%) · 8 kWh: 6.000 siklus (≥ 70%) · 15 kWh: hingga 8.000 siklus (≥ 70%) @ 25 °C, 0,5C',
                'Input PLN' => '220/230 V · rentang 170–280 VAC ±2% · 50/60 Hz · efisiensi 95% · bypass maks 40 A',
                'Output inverter' => 'Pure sine wave · 220/230/240 V ±5% · 50/60 Hz ±0,3 Hz · power factor 1 · efisiensi 92%',
                'Charging surya (MPPT)' => 'PV Voc maks 500 Vdc · rentang kerja 60–500 Vdc · arus PV maks 20 A',
                'Komunikasi' => 'RS485 / RS232 / CAN · layar LCD',
                'Proteksi' => 'BMS · overvoltage · overcurrent · overcharge',
                'Garansi' => 'Baterai 10 tahun · inverter 2 tahun',
            ],
            'intro' => '<p><strong>ESS All-in-One UBetter</strong> — baterai dan inverter sudah jadi satu kabinet, jadi pemasangan lebih cepat dan rapi: tinggal sambungkan panel surya dan jalur listrik rumah. Pilih kapasitas baterai & daya inverter sesuai beban.</p>',
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function description(array $row): string
    {
        $html = $row['intro'];
        if (! empty($row['variants'])) {
            $rows = '';
            foreach ($row['variants'] as $v) {
                $extra = $v['detail'] ?? '—';
                $rows .= '<tr><td><strong>'.e($v['name']).'</strong></td><td>'.e($extra).'</td><td>'.rupiah($v['price']).'</td></tr>';
            }
            $html .= '<p><strong>Pilihan '.e(strtolower($row['option'])).':</strong></p><table><thead><tr><th>'.e($row['option']).'</th><th>Keterangan</th><th>Harga</th></tr></thead><tbody>'.$rows.'</tbody></table>';
        }

        return $html.'
<ul>
<li>✅ Garansi resmi '.e($row['warranty']).'.</li>
<li>📦 Stok gudang Jakarta, siap kirim'.(($row['freight'] ?? false) ? ' via kargo (ongkir dikonfirmasi admin)' : '').'.</li>
<li>🧑‍🔧 Butuh hitungan kapasitas, kecocokan inverter–baterai, atau pemasangan? <a href="/konsultasi">Konsultasi gratis</a> atau <a href="/permintaan-penawaran">minta penawaran proyek</a>.</li>
</ul>';
    }

    /** @param  array<string, string>  $specs */
    private function table(array $specs): string
    {
        $rows = '';
        foreach ($specs as $label => $value) {
            $rows .= '<tr><th>'.e($label).'</th><td>'.e($value).'</td></tr>';
        }

        return '<table><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * Galeri: gambar berlogo (spesifikasi resmi) hanya dioptimalkan; foto produk polos diberi
     * watermark. Varian yang punya gambar sendiri (variant_images) memakai gambar itu.
     *
     * @param  list<array{0: string, 1: bool, 2: string}>  $images
     */
    private function syncImages(Product $product, SeedGuard $guard, array $images, array $variantImages): void
    {
        $watermark = app(WatermarkService::class);
        $disk = Storage::disk('public');
        $stored = [];
        foreach ($images as $i => [$file, $branded, $alt]) {
            $src = self::ASSETS.'/'.$file;
            if (! is_file($src)) {
                continue;
            }
            $stem = 'products/ubetter/'.pathinfo($file, PATHINFO_FILENAME);
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
            if ($guard->shouldAdd('images', $file, $product->images()->where('path', $final)->exists())) {
                $product->images()->create(['path' => $final, 'alt' => $alt, 'sort_order' => $i, 'watermarked_at' => $watermark->isSupported() ? now() : null]);
            }
            if ($product->images()->where('path', $final)->exists()) {
                $stored[$file] = $final;
            }
        }
        // Urutan galeri mengikuti daftar & gambar pertama = foto utama — selama admin
        // belum mengganti foto utamanya sendiri.
        if ($stored && $guard->apply($product, ['main_image_path' => reset($stored)])) {
            $product->save();
            foreach (array_values($stored) as $i => $path) {
                $product->images()->where('path', $path)->update(['sort_order' => $i]);
            }
        }

        foreach ($variantImages as $variantSku => $file) {
            if (isset($stored[$file])) {
                ProductVariant::where('sku', $variantSku)->whereNull('image_path')->update(['image_path' => $stored[$file]]);
            }
        }
    }

    private function attachDocument(Product $product, SeedGuard $guard, string $title, string $file): void
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src) || ! $guard->shouldAdd('docs', $title, $product->documents()->where('title', $title)->exists())) {
            return;
        }
        $stored = 'products/docs/ubetter-'.$file;
        if (! Storage::disk('public')->exists($stored)) {
            Storage::disk('public')->put($stored, (string) file_get_contents($src));
        }
        $product->documents()->create(['type' => 'brosur', 'title' => $title, 'path' => $stored]);
    }

    private function setStock(Product $product, ?ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)->where('product_variant_id', $variant?->id)->sum('quantity_available');
        if ($target - $current !== 0) {
            app(StockService::class)->adjust($product, $variant, $target - $current, StockMovementType::Purchase, note: 'Stok awal UBetter (stocklist supplier Agustus 2026)');
        }
    }
}
