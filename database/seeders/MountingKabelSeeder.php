<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;
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
        // --- Kabel NYAF Jembo (per meter, modal × 1,3) ---
        [
            'slug' => 'kabel-nyaf-jembo-4mm-merah-per-meter',
            'sku' => 'NYAF-4MM-MERAH',
            'name' => 'Kabel Listrik NYAF Jembo 4mm² Merah (Per Meter)',
            'brand' => 'Jembo', 'category' => 'kabel-konektor-proteksi',
            'model' => 'NYAF 1×4mm²',
            'price' => 10600, 'cost' => 8078.89, 'stock' => 63,
            'unit' => 'meter', 'weight' => 45, 'dims' => [15, 15, 2],
            'short' => 'Kabel NYAF Jembo inti tunggal serabut 4mm² warna merah — fleksibel untuk instalasi panel listrik, wiring inverter & baterai. Dijual per meter (qty = jumlah meter).',
            'specs' => [
                'Jenis' => 'NYAF — tembaga serabut fleksibel, isolasi PVC',
                'Penampang' => '1 × 4mm²',
                'Merek' => 'Jembo',
                'Warna' => 'Merah',
                'Satuan Jual' => 'Per meter (qty = jumlah meter)',
                'Kegunaan' => 'Wiring panel listrik, inverter, baterai (umum dipakai jalur positif)',
            ],
        ],
        [
            'slug' => 'kabel-nyaf-jembo-4mm-hitam-per-meter',
            'sku' => 'NYAF-4MM-HITAM',
            'name' => 'Kabel Listrik NYAF Jembo 4mm² Hitam (Per Meter)',
            'brand' => 'Jembo', 'category' => 'kabel-konektor-proteksi',
            'model' => 'NYAF 1×4mm²',
            'price' => 10600, 'cost' => 8074, 'stock' => 61,
            'unit' => 'meter', 'weight' => 45, 'dims' => [15, 15, 2],
            'short' => 'Kabel NYAF Jembo inti tunggal serabut 4mm² warna hitam — fleksibel untuk instalasi panel listrik, wiring inverter & baterai. Dijual per meter (qty = jumlah meter).',
            'specs' => [
                'Jenis' => 'NYAF — tembaga serabut fleksibel, isolasi PVC',
                'Penampang' => '1 × 4mm²',
                'Merek' => 'Jembo',
                'Warna' => 'Hitam',
                'Satuan Jual' => 'Per meter (qty = jumlah meter)',
                'Kegunaan' => 'Wiring panel listrik, inverter, baterai (umum dipakai jalur negatif)',
            ],
        ],

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

    public function run(): void
    {
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
                    'requires_freight' => false,
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
