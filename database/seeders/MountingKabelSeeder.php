<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aksesoris mounting (support module) + kabel NYAF dari stok gudang.
 * Aturan harga (owner, Sep 2026): mounting = modal × 1,7 dan kabel = modal
 * × 1,3, dibulatkan KE ATAS ke kelipatan Rp 100 (warna kabel yang sama
 * diseragamkan ke harga tertinggi).
 *
 * Idempotent (firstOrCreate): aman dijalankan ulang, tidak menimpa produk
 * yang sudah diedit admin; stok awal hanya di-set saat produk pertama
 * dibuat (sesuai stok gudang saat input). Foto via Admin → Produk → Edit.
 */
class MountingKabelSeeder extends Seeder
{
    private const ROWS = [
        // --- Support module / aksesoris mounting (modal × 1,7) ---
        [
            'slug' => 'cable-clip-rekasurya-mr-is-cc',
            'sku' => 'MR-IS-CC',
            'name' => 'Cable Clip Rekasurya MR-IS-CC',
            'brand' => null, 'category' => 'mounting-rangka',
            'model' => 'MR-IS-CC',
            'price' => 4000, 'cost' => 2315.58, 'stock' => 182,
            'unit' => 'pcs', 'weight' => 15, 'dims' => [5, 3, 2],
            'short' => 'Klip perapi kabel untuk rangka mounting panel surya — menjaga kabel PV tertata rapi di sepanjang rel.',
            'specs' => [
                'Jenis' => 'Cable clip (klip kabel mounting)',
                'Model' => 'MR-IS-CC',
                'Kegunaan' => 'Merapikan kabel PV pada rel mounting panel surya',
            ],
        ],
        [
            'slug' => 'end-clamp-antai-cg-018-35-40',
            'sku' => 'ANTAI-CG-018',
            'name' => 'End Clamp ANTAI CG-018 (Frame 35/40mm)',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'CG-018',
            'price' => 14100, 'cost' => 8237.38, 'stock' => 313,
            'unit' => 'pcs', 'weight' => 90, 'dims' => [6, 4, 4],
            'short' => 'Penjepit ujung (end clamp) ANTAI CG-018 untuk panel surya frame 35/40mm — mengunci panel paling pinggir ke rel mounting.',
            'specs' => [
                'Jenis' => 'End clamp (penjepit ujung panel)',
                'Model' => 'CG-018',
                'Kompatibel' => 'Frame panel tebal 35mm / 40mm',
                'Bahan' => 'Aluminium anodized + baut stainless (umum aksesoris ANTAI)',
                'Kebutuhan Umum' => '4 pcs per baris panel (2 di tiap ujung)',
            ],
        ],
        [
            'slug' => 'mid-clamp-antai-gn-003',
            'sku' => 'ANTAI-GN-003',
            'name' => 'Mid Clamp ANTAI GN-003',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'GN-003',
            'price' => 13500, 'cost' => 7924.67, 'stock' => 1077,
            'unit' => 'pcs', 'weight' => 90, 'dims' => [6, 4, 4],
            'short' => 'Penjepit tengah (mid clamp) ANTAI GN-003 — mengunci sisi antar dua panel surya yang bersebelahan pada rel mounting.',
            'specs' => [
                'Jenis' => 'Mid clamp (penjepit antar panel)',
                'Model' => 'GN-003',
                'Bahan' => 'Aluminium anodized + baut stainless (umum aksesoris ANTAI)',
                'Kebutuhan Umum' => '2 pcs di setiap pertemuan dua panel',
            ],
        ],
        [
            'slug' => 'grounding-clip-antai-at-ec-01',
            'sku' => 'ANTAI-AT-EC-01',
            'name' => 'Grounding Clip ANTAI AT-EC-01 (Earthing Clip)',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'AT-EC-01',
            'price' => 2100, 'cost' => 1198.97, 'stock' => 50,
            'unit' => 'pcs', 'weight' => 15, 'dims' => [4, 3, 1],
            'short' => 'Klip grounding/earthing ANTAI AT-EC-01 — menyambungkan pentanahan antar frame panel dan rel mounting demi keamanan instalasi.',
            'specs' => [
                'Jenis' => 'Grounding / earthing clip',
                'Model' => 'AT-EC-01',
                'Kegunaan' => 'Kontinuitas pentanahan antar panel & rel mounting',
            ],
        ],
        [
            'slug' => 'cable-clip-antai-at-rc-01-4mm',
            'sku' => 'ANTAI-AT-RC-01',
            'name' => 'Cable Clip ANTAI AT-RC-01 (Kabel 4mm)',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'AT-RC-01',
            'price' => 2600, 'cost' => 1473.83, 'stock' => 450,
            'unit' => 'pcs', 'weight' => 10, 'dims' => [4, 3, 1],
            'short' => 'Klip kabel ANTAI AT-RC-01 untuk kabel 4mm — menjepit kabel PV rapi ke frame/rel panel surya, tahan cuaca luar ruang.',
            'specs' => [
                'Jenis' => 'Cable clip (klip kabel)',
                'Model' => 'AT-RC-01',
                'Ukuran Kabel' => '± 4mm',
                'Kegunaan' => 'Merapikan kabel PV pada frame panel / rel mounting',
            ],
        ],
        [
            'slug' => 'konektor-mc4-sepasang-male-female',
            'sku' => 'MC4-PAIR',
            'name' => 'Konektor MC4 Sepasang (Male + Female)',
            'brand' => null, 'category' => 'kabel-konektor-proteksi-konektor-mc4',
            'model' => 'MC4',
            'price' => 20800, 'cost' => 16000, 'stock' => 0,
            'unit' => 'pasang', 'weight' => 50, 'dims' => [6, 4, 3],
            'short' => 'Konektor MC4 sepasang (1 male + 1 female) — sambungan standar kabel PV ke panel surya, SCC, atau inverter. Kedap air, tinggal crimping ke kabel PV 4/6mm².',
            'specs' => [
                'Jenis' => 'Konektor MC4 (standar sambungan PV)',
                'Isi' => '1 pasang: 1 male + 1 female',
                'Kompatibel' => 'Kabel PV 4mm² / 6mm²',
                'Kegunaan' => 'Sambungan panel surya ↔ kabel PV ↔ SCC/inverter, kedap air',
            ],
        ],
        [
            'slug' => 'aluminium-rail-antai-cg-010-2600mm',
            'sku' => 'ANTAI-CG-010',
            'name' => 'Aluminium Rail ANTAI CG-010 (2600mm)',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'CG-010',
            'price' => 204000, 'cost' => 120000, 'stock' => 0,
            'unit' => 'pcs', 'weight' => 2800, 'dims' => [260, 5, 4],
            'freight' => true,
            'short' => 'Rel aluminium ANTAI CG-010 panjang 2.600mm — tulang punggung mounting panel surya: panel dijepit mid/end clamp ke rel ini, rel bertumpu pada roof hook / L feet.',
            'specs' => [
                'Jenis' => 'Rel mounting aluminium (solar rail)',
                'Model' => 'CG-010',
                'Panjang' => '2.600 mm',
                'Bahan' => 'Aluminium anodized (umum aksesoris ANTAI)',
                'Kegunaan' => 'Dudukan panel surya — dipadukan dengan mid clamp, end clamp, T-nut, roof hook / L feet',
                'Catatan Kirim' => 'Barang panjang 2,6 m — dikirim via kargo',
            ],
        ],
        [
            'slug' => 'roof-hook-antai-pantile',
            'sku' => 'ANTAI-PANTILE-RH',
            'name' => 'Roof Hook ANTAI Pantile (Atap Genteng)',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'Pantile Roof Hook',
            'price' => 74900, 'cost' => 44003.39, 'stock' => 42,
            'unit' => 'pcs', 'weight' => 500, 'dims' => [20, 10, 8],
            'short' => 'Kait atap (roof hook) ANTAI untuk atap genteng (pantile) — tumpuan rel mounting panel surya tanpa melubangi genteng, dikaitkan ke reng/rangka atap.',
            'specs' => [
                'Jenis' => 'Roof hook untuk atap genteng (pantile)',
                'Model' => 'Pantile Roof Hook',
                'Kegunaan' => 'Tumpuan rel mounting panel surya di atap genteng — genteng tetap utuh',
                'Bahan' => 'Stainless steel (umum roof hook ANTAI)',
            ],
        ],
        [
            'slug' => 't-nut-antai-m8-25mm',
            'sku' => 'ANTAI-TNUT-M8-25',
            'name' => 'T-Nut ANTAI M8×25mm (Aksesoris L Feet / Rel)',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'T-Nut M8×25mm',
            'price' => 4100, 'cost' => 2361.49, 'stock' => 100,
            'unit' => 'pcs', 'weight' => 25, 'dims' => [4, 2, 2],
            'short' => 'Mur T (T-nut) ANTAI M8×25mm — pengikat L feet dan aksesoris ke slot rel mounting panel surya, tinggal selipkan dan putar.',
            'specs' => [
                'Jenis' => 'T-nut (mur T slot rel)',
                'Ukuran' => 'M8 × 25mm',
                'Kegunaan' => 'Mengikat L feet / klem / aksesoris ke slot rel mounting',
            ],
        ],
        [
            'slug' => 'tile-hook-antai-atl-fwny-05-l-feet',
            'sku' => 'ANTAI-ATL-FWNY-05',
            'name' => 'Tile Hook ANTAI ATL-FWNY-05 (L Feet)',
            'brand' => 'ANTAI', 'category' => 'mounting-rangka',
            'model' => 'ATL-FWNY-05',
            'price' => 21300, 'cost' => 12482.06, 'stock' => 32,
            'unit' => 'pcs', 'weight' => 250, 'dims' => [12, 6, 6],
            'short' => 'Kaki dudukan (L feet / tile hook) ANTAI ATL-FWNY-05 — tumpuan rel mounting panel surya ke atap.',
            'specs' => [
                'Jenis' => 'Tile hook / L feet (kaki dudukan rel)',
                'Model' => 'ATL-FWNY-05',
                'Kegunaan' => 'Tumpuan rel mounting ke rangka/permukaan atap',
            ],
        ],
    ];

    /** Varian warna NYAF: [sku varian, warna, modal, stok awal, slug produk lama]. */
    private const NYAF_VARIANTS = [
        ['NYAF-4MM-MERAH', 'Merah', 8078.89, 63, 'kabel-nyaf-jembo-4mm-merah-per-meter'],
        ['NYAF-4MM-HITAM', 'Hitam', 8074.00, 61, 'kabel-nyaf-jembo-4mm-hitam-per-meter'],
    ];

    private const NYAF_PRICE = 10600; // modal tertinggi × 1,3 → bulat atas Rp 100, semua warna sama

    /** Varian arus MCB DC Suntree — semua harga sama (owner, Sep 2026). */
    private const MCB_AMPERES = ['10A', '16A', '32A', '63A'];

    private const MCB_PRICE = 215000;

    public function run(): void
    {
        $this->nyafVariableProduct();
        $this->mcbVariableProduct();

        foreach (self::ROWS as $row) {
            $category = Category::where('slug', $row['category'])->first();
            $brand = $row['brand']
                ? Brand::firstOrCreate(['slug' => Str::slug($row['brand'])], ['name' => $row['brand'], 'is_active' => true])
                : null;

            $specRows = '';
            foreach ($row['specs'] as $label => $value) {
                $specRows .= "<tr><th>{$label}</th><td>{$value}</td></tr>\n";
            }

            $perMeter = $row['unit'] === 'meter';
            $description = '<p><strong>'.$row['name'].'</strong><br>'.$row['short']
                .($perMeter ? ' Jumlah di keranjang = panjang kabel dalam meter.' : '')
                .'</p><p>Cocok melengkapi pembelian panel surya, rel mounting, dan kabel PV di '.brand().'.</p>';

            $product = Product::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'category_id' => $category?->id,
                    'brand_id' => $brand?->id,
                    'model' => $row['model'],
                    'product_type' => 'simple',
                    'condition' => 'new',
                    'short_description' => $row['short'],
                    'description' => $description,
                    'specifications' => "<table><tbody>\n{$specRows}</tbody></table>",
                    'price' => $row['price'],
                    'cost_price' => $row['cost'],
                    'unit' => $row['unit'],
                    'weight_grams' => $row['weight'], // ± estimasi
                    'length_cm' => $row['dims'][0],
                    'width_cm' => $row['dims'][1],
                    'height_cm' => $row['dims'][2],
                    'requires_freight' => $row['freight'] ?? false,
                    'is_new' => true,
                    'status' => 'published',
                    'published_at' => now(),
                    'keywords' => mb_strtolower($row['name']).', aksesoris mounting panel surya, support module plts, '.mb_strtolower($row['model']),
                    'meta_title' => $row['name'].' — Aksesoris PLTS',
                    'meta_description' => 'Jual '.$row['name'].' Rp '.number_format($row['price'], 0, ',', '.').($perMeter ? '/meter' : '').'. Aksesoris instalasi panel surya, kirim ke seluruh Indonesia.',
                ],
            );

            // Stok awal sesuai catatan gudang — hanya saat produk baru dibuat.
            if ($product->wasRecentlyCreated) {
                $this->setStock($product, $row['stock']);
            }

            $this->command?->info($row['name'].' '.($product->wasRecentlyCreated ? 'ditambahkan (stok '.$row['stock'].' '.$row['unit'].')' : 'sudah ada, dilewati').' — Rp '.number_format($row['price'], 0, ',', '.'));
        }

        $this->command?->warn('Upload foto produk via Admin → Produk → Edit. Berat/dimensi berupa estimasi — sesuaikan bila perlu.');
    }

    /**
     * Kabel NYAF Jembo 4mm² sebagai SATU produk dengan varian warna
     * (Merah/Hitam, harga sama). Versi awal seeder membuat 2 produk terpisah —
     * di sini dikonversi: stok pindah ke varian, produk lama tanpa pesanan
     * dihapus, yang pernah dipesan diarsipkan.
     */
    private function nyafVariableProduct(): void
    {
        $category = Category::where('slug', 'kabel-konektor-proteksi')->first();
        $brand = Brand::firstOrCreate(['slug' => 'jembo'], ['name' => 'Jembo', 'is_active' => true]);

        $description = '<p><strong>Kabel Listrik NYAF Jembo 4mm² (Per Meter)</strong><br>'
            .'Kabel NYAF Jembo inti tunggal tembaga serabut 4mm², fleksibel untuk instalasi panel listrik, wiring inverter &amp; baterai. '
            .'Pilih warna <strong>Merah</strong> (umum jalur positif) atau <strong>Hitam</strong> (umum jalur negatif) di pilihan varian. '
            .'Dijual <strong>per meter</strong> — jumlah di keranjang = panjang kabel dalam meter.</p>';

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Jenis</th><td>NYAF — tembaga serabut fleksibel, isolasi PVC</td></tr>
<tr><th>Penampang</th><td>1 × 4mm²</td></tr>
<tr><th>Merek</th><td>Jembo</td></tr>
<tr><th>Warna</th><td>Merah atau Hitam (pilih varian)</td></tr>
<tr><th>Satuan Jual</th><td>Per meter (qty = jumlah meter)</td></tr>
<tr><th>Kegunaan</th><td>Wiring panel listrik, inverter, baterai</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'kabel-nyaf-jembo-4mm-per-meter'],
            [
                'sku' => 'NYAF-4MM',
                'name' => 'Kabel Listrik NYAF Jembo 4mm² (Per Meter)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'NYAF 1×4mm²',
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'Kabel NYAF Jembo inti tunggal serabut 4mm², pilih warna Merah atau Hitam. Fleksibel untuk wiring panel listrik, inverter & baterai. Dijual per meter (qty = jumlah meter).',
                'description' => $description,
                'specifications' => $specifications,
                'price' => self::NYAF_PRICE,
                'cost_price' => 8078.89, // modal tertinggi antar warna (Merah)
                'unit' => 'meter',
                'weight_grams' => 45, // ± per meter (estimasi)
                'length_cm' => 15,
                'width_cm' => 15,
                'height_cm' => 2,
                'requires_freight' => false,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'keywords' => 'kabel nyaf, kabel jembo 4mm, kabel listrik serabut, kabel inverter, kabel baterai, nyaf 4mm merah, nyaf 4mm hitam, kabel per meter',
                'meta_title' => 'Kabel Listrik NYAF Jembo 4mm² Merah / Hitam — Per Meter',
                'meta_description' => 'Jual kabel NYAF Jembo 4mm² warna merah & hitam, Rp '.number_format(self::NYAF_PRICE, 0, ',', '.').'/meter. Fleksibel untuk wiring inverter, baterai, panel listrik.',
            ],
        );

        foreach (self::NYAF_VARIANTS as [$sku, $warna, $cost, $stock, $legacySlug]) {
            $variant = $product->variants()->firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $warna,
                    'option_values' => ['Warna' => $warna],
                    'price' => self::NYAF_PRICE,
                    'weight_grams' => 45,
                    'is_active' => true,
                    'sort_order' => $warna === 'Merah' ? 0 : 1,
                ],
            );

            if (! $variant->wasRecentlyCreated) {
                continue;
            }

            // Konversi produk lama: stoknya diserap, kalau tidak ada produk
            // lama (instalasi baru) pakai stok catatan gudang.
            $legacy = Product::where('slug', $legacySlug)->first();
            if ($legacy) {
                $moved = (int) WarehouseStock::where('product_id', $legacy->id)->sum('quantity_available');
                if ($moved > 0) {
                    app(StockService::class)->adjust($product, $variant, $moved, StockMovementType::Purchase, note: 'Migrasi stok dari produk lama '.$legacySlug);
                }
                if (DB::table('order_items')->where('product_id', $legacy->id)->exists()) {
                    $legacy->forceFill(['status' => 'archived'])->save();
                    $this->command?->warn($legacySlug.' diarsipkan (punya riwayat pesanan); stok '.$moved.' m dipindah ke varian '.$warna.'.');
                } else {
                    $legacy->delete();
                    $this->command?->info($legacySlug.' dihapus, stok '.$moved.' m dipindah ke varian '.$warna.'.');
                }
            } else {
                app(StockService::class)->adjust($product, $variant, $stock, StockMovementType::Purchase, note: 'Stok awal (seeder, sesuai catatan gudang)');
                $this->command?->info('Varian NYAF '.$warna.' dibuat dengan stok awal '.$stock.' meter.');
            }
        }
    }

    /**
     * DC MCB Suntree 2 Pole 550VDC (seri SL7N) sebagai SATU produk dengan
     * varian arus — pengaman string PV / jalur baterai. Semua varian satu
     * harga; modal belum dicatat (diisi owner via Harga & Margin). Stok per
     * varian diisi admin.
     */
    private function mcbVariableProduct(): void
    {
        $category = Category::where('slug', 'kabel-konektor-proteksi-mcb-mccb-dc')->first()
            ?? Category::where('slug', 'kabel-konektor-proteksi')->first();
        $brand = Brand::firstOrCreate(['slug' => 'suntree'], ['name' => 'Suntree', 'is_active' => true]);

        $description = '<p><strong>DC MCB Suntree 2 Pole 550 VDC</strong><br>'
            .'Pemutus arus (MCB) khusus DC dari Suntree, 2 pole, tegangan kerja hingga <strong>550 VDC</strong> — pengaman string panel surya dan jalur baterai/SCC/inverter. '
            .'Pilih arus sesuai kebutuhan di pilihan varian: '.implode(', ', self::MCB_AMPERES).'. '
            .'Pilih rating ±1,25× arus kerja string; kalau ragu, tanyakan ke Kirana atau tim kami.</p>';

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Jenis</th><td>MCB DC (pemutus arus khusus DC)</td></tr>
<tr><th>Merek / Seri</th><td>Suntree SL7N</td></tr>
<tr><th>Pole</th><td>2 pole</td></tr>
<tr><th>Tegangan Kerja</th><td>Hingga 550 VDC</td></tr>
<tr><th>Pilihan Arus</th><td>10A · 16A · 32A · 63A (pilih varian)</td></tr>
<tr><th>Berat Satuan</th><td>± 325 g</td></tr>
<tr><th>Kegunaan</th><td>Proteksi string panel surya, jalur baterai / SCC / inverter DC</td></tr>
</tbody></table>
HTML;

        $product = Product::firstOrCreate(
            ['slug' => 'dc-mcb-suntree-2-pole-550vdc'],
            [
                'sku' => 'SUNTREE-SL7N',
                'name' => 'DC MCB Suntree 2 Pole 550 VDC (10A–63A)',
                'category_id' => $category?->id,
                'brand_id' => $brand->id,
                'model' => 'SL7N',
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => 'MCB khusus DC Suntree 2 pole 550 VDC untuk proteksi string panel surya & jalur baterai. Pilih arus: 10A, 16A, 32A, atau 63A — semua satu harga.',
                'description' => $description,
                'specifications' => $specifications,
                'price' => self::MCB_PRICE,
                'unit' => 'pcs',
                'weight_grams' => 325,
                'length_cm' => 9,
                'width_cm' => 4,
                'height_cm' => 8,
                'requires_freight' => false,
                'is_new' => true,
                'status' => 'published',
                'published_at' => now(),
                'keywords' => 'mcb dc, dc mcb suntree, mcb 550vdc, mcb 2 pole, proteksi panel surya, breaker dc, sl7n, mcb plts',
                'meta_title' => 'DC MCB Suntree 2 Pole 550 VDC — 10A / 16A / 32A / 63A',
                'meta_description' => 'Jual DC MCB Suntree SL7N 2 pole 550 VDC, pilihan arus 10A–63A, Rp '.number_format(self::MCB_PRICE, 0, ',', '.').'/pcs. Proteksi string panel surya & jalur baterai.',
            ],
        );

        foreach (self::MCB_AMPERES as $i => $ampere) {
            $product->variants()->firstOrCreate(
                ['sku' => 'SUNTREE-SL7N-'.$ampere],
                [
                    'name' => $ampere,
                    'option_values' => ['Arus' => $ampere],
                    'price' => self::MCB_PRICE,
                    'weight_grams' => 325,
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
        }

        $this->command?->info('DC MCB Suntree 2 Pole 550VDC (varian '.implode('/', self::MCB_AMPERES).') siap — Rp '.number_format(self::MCB_PRICE, 0, ',', '.').'.');
    }

    private function setStock(Product $product, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, null, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder, sesuai catatan gudang)');
        }
    }
}
