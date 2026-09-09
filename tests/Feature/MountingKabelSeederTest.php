<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use Database\Seeders\MountingKabelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aksesoris mounting + kabel NYAF: markup terkunci (mounting ≥ modal × 1,7,
 * kabel ≥ modal × 1,3), stok awal sesuai catatan gudang, idempotent.
 */
class MountingKabelSeederTest extends TestCase
{
    use RefreshDatabase;

    /** slug => [modal, harga, stok, markup minimal]. */
    private const ROWS = [
        'cable-clip-rekasurya-mr-is-cc' => [2315.58, 4000, 182, 1.7],
        'end-clamp-antai-cg-018-35-40' => [8237.38, 14100, 313, 1.7],
        'mid-clamp-antai-gn-003' => [7924.67, 13500, 1077, 1.7],
        'grounding-clip-antai-at-ec-01' => [1198.97, 2100, 50, 1.7],
        'cable-clip-antai-at-rc-01-4mm' => [1473.83, 2600, 450, 1.7],
        'tile-hook-antai-atl-fwny-05-l-feet' => [12482.06, 21300, 32, 1.7],
        'konektor-mc4-sepasang-male-female' => [16000.00, 20800, 0, 1.3],
        'aluminium-rail-antai-cg-010-2600mm' => [120000.00, 204000, 0, 1.7],
        'roof-hook-antai-pantile' => [44003.39, 74900, 42, 1.7],
        't-nut-antai-m8-25mm' => [2361.49, 4100, 100, 1.7],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultWarehouse();
        Category::factory()->create(['name' => 'Mounting & Rangka', 'slug' => 'mounting-rangka']);
        Category::factory()->create(['name' => 'Kabel, Konektor & Proteksi', 'slug' => 'kabel-konektor-proteksi']);
        Category::factory()->create(['name' => 'Konektor MC4', 'slug' => 'kabel-konektor-proteksi-konektor-mc4']);
        $this->seed(MountingKabelSeeder::class);
    }

    public function test_all_items_exist_with_locked_prices_markup_and_stock(): void
    {
        foreach (self::ROWS as $slug => [$cost, $price, $stock, $markup]) {
            $p = Product::where('slug', $slug)->first();
            $this->assertNotNull($p, $slug);
            $this->assertSame((float) $price, (float) $p->price, $slug);
            $this->assertSame($cost, (float) $p->cost_price, $slug);
            // Harga jual ≥ modal × markup (mounting 1,7 / kabel 1,3).
            $this->assertGreaterThanOrEqual($cost * $markup, (float) $p->price, $slug);
            $this->assertSame($stock, (int) $p->fresh()->stock, $slug);
            $this->assertSame('published', $p->status, $slug);
        }
    }

    public function test_nyaf_is_one_variable_product_with_color_variants(): void
    {
        $p = Product::where('slug', 'kabel-nyaf-jembo-4mm-per-meter')->firstOrFail();

        $this->assertSame('variable', $p->product_type);
        $this->assertSame('meter', $p->unit);
        $this->assertSame('Jembo', $p->brand?->name);
        $this->assertCount(2, $p->variants);

        // Harga semua warna sama & margin terkunci: modal tertinggi × 1,3.
        foreach ([['NYAF-4MM-MERAH', 8078.89, 63], ['NYAF-4MM-HITAM', 8074.00, 61]] as [$sku, $cost, $stock]) {
            $v = $p->variants()->where('sku', $sku)->first();
            $this->assertSame(10600.0, (float) $v->price, $sku);
            $this->assertGreaterThanOrEqual($cost * 1.3, (float) $v->price, $sku);
            $this->assertSame($stock, (int) $v->fresh()->stock, $sku);
        }

        $this->assertSame('pcs', Product::where('slug', 'mid-clamp-antai-gn-003')->value('unit'));
    }

    /** MCB DC Suntree: satu produk variabel, 4 varian arus, semua satu harga. */
    public function test_mcb_is_one_variable_product_with_ampere_variants(): void
    {
        $p = Product::where('slug', 'dc-mcb-suntree-2-pole-550vdc')->firstOrFail();

        $this->assertSame('variable', $p->product_type);
        $this->assertSame('Suntree', $p->brand?->name);
        $this->assertCount(4, $p->variants);

        foreach (['10A', '16A', '32A', '63A'] as $ampere) {
            $v = $p->variants()->where('sku', 'SUNTREE-SL7N-'.$ampere)->first();
            $this->assertNotNull($v, $ampere);
            $this->assertSame(215000.0, (float) $v->price, $ampere);
        }

        // Re-run tidak menggandakan varian.
        $this->seed(MountingKabelSeeder::class);
        $this->assertCount(4, $p->fresh()->variants);
    }

    /** Produksi terlanjur punya 2 produk NYAF terpisah — stoknya diserap varian. */
    public function test_legacy_nyaf_products_are_absorbed_with_their_stock(): void
    {
        // Bersihkan hasil setUp lalu mainkan skenario produksi: produk lama ada.
        Product::where('slug', 'kabel-nyaf-jembo-4mm-per-meter')->forceDelete();
        $legacy = Product::factory()->create([
            'slug' => 'kabel-nyaf-jembo-4mm-merah-per-meter',
            'sku' => 'NYAF-4MM-MERAH-LAMA',
            'name' => 'Kabel Listrik NYAF Jembo 4mm² Merah (Per Meter)',
            'status' => 'published', 'stock' => 0,
        ]);
        app(StockService::class)->adjust($legacy, null, 63, StockMovementType::Purchase);

        $this->seed(MountingKabelSeeder::class);

        $this->assertNull(Product::where('slug', 'kabel-nyaf-jembo-4mm-merah-per-meter')->first());
        $variant = Product::where('slug', 'kabel-nyaf-jembo-4mm-per-meter')->firstOrFail()
            ->variants()->where('sku', 'NYAF-4MM-MERAH')->first();
        $this->assertSame(63, (int) $variant->fresh()->stock);
    }

    public function test_antai_brand_is_attached(): void
    {
        $p = Product::where('slug', 'mid-clamp-antai-gn-003')->first();

        $this->assertSame('ANTAI', $p->brand?->name);
        $this->assertSame('mounting-rangka', $p->category?->slug);
    }

    public function test_rerun_does_not_reset_admin_stock_or_price(): void
    {
        $p = Product::where('slug', 'end-clamp-antai-cg-018-35-40')->first();
        $p->forceFill(['price' => 15000])->save();

        $this->seed(MountingKabelSeeder::class);

        $this->assertSame(15000.0, (float) $p->fresh()->price);
        $this->assertSame(313, (int) $p->fresh()->stock);
        $this->assertSame(10, Product::whereIn('slug', array_keys(self::ROWS))->count());
        // Rel 2,6 m wajib jalur kargo.
        $this->assertTrue((bool) Product::where('slug', 'aluminium-rail-antai-cg-010-2600mm')->value('requires_freight'));
        // Re-run tidak menggandakan produk/varian NYAF.
        $this->assertSame(1, Product::where('slug', 'like', 'kabel-nyaf-jembo-4mm%')->count());
        $this->assertCount(2, Product::where('slug', 'kabel-nyaf-jembo-4mm-per-meter')->first()->variants);
    }
}
