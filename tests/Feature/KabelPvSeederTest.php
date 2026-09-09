<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use Database\Seeders\KabelPvSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kabel PV per meter: satu produk variabel (4mm² & 6mm²), harga & margin
 * terkunci, hanya hitam, konversi dari 2 produk lama, idempotent.
 */
class KabelPvSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Modal per meter (referensi owner) — margin dari revenue wajib ≥ 20%. */
    private const COST = ['KBL-PV-4MM-BLK' => 14000, 'KBL-PV-6MM-BLK' => 19000];

    private const PRICE = ['KBL-PV-4MM-BLK' => 18000, 'KBL-PV-6MM-BLK' => 24000];

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultWarehouse();
        Category::factory()->create(['name' => 'Kabel PV', 'slug' => 'kabel-konektor-proteksi-kabel-pv']);
    }

    private function parent(): Product
    {
        return Product::where('slug', 'kabel-pv-dc-solar-hitam-per-meter')->firstOrFail();
    }

    public function test_one_variable_product_with_both_sizes_and_locked_margins(): void
    {
        $this->seed(KabelPvSeeder::class);

        $p = $this->parent();
        $this->assertSame('variable', $p->product_type);
        $this->assertSame('meter', $p->unit);
        $this->assertSame(18000.0, (float) $p->price); // "mulai dari" varian termurah
        $this->assertCount(2, $p->variants);

        foreach ($p->variants as $v) {
            $this->assertSame((float) self::PRICE[$v->sku], (float) $v->price, $v->sku);
            // Modal per varian tercatat (dasar margin di halaman Harga & Margin).
            $this->assertSame((float) self::COST[$v->sku], (float) $v->cost_price, $v->sku);
            // Margin dari revenue ≥ 20% → harga ≥ modal ÷ 0,8.
            $this->assertGreaterThanOrEqual(self::COST[$v->sku] / 0.8, (float) $v->price, $v->sku);
        }
    }

    public function test_only_black_and_per_meter_is_stated(): void
    {
        $this->seed(KabelPvSeeder::class);
        $p = $this->parent();

        $this->assertStringContainsString('hanya tersedia hitam', mb_strtolower($p->specifications));
        $this->assertStringContainsString('per meter', mb_strtolower($p->short_description));
        $this->assertStringContainsString('Hitam', $p->name);
    }

    /** Produksi sudah terlanjur punya 2 produk terpisah — dikonversi ke varian. */
    public function test_legacy_simple_products_are_absorbed_with_their_stock(): void
    {
        $legacy = Product::factory()->create([
            'slug' => 'kabel-pv-dc-solar-1x4mm-hitam-per-meter',
            'sku' => 'KBL-PV-4MM-BLK-LAMA',
            'name' => 'Kabel PV DC Solar 1×4mm² Hitam (Per Meter)',
            'status' => 'published',
            'stock' => 0,
        ]);
        app(StockService::class)->adjust($legacy, null, 40, StockMovementType::Purchase);

        $this->seed(KabelPvSeeder::class);

        // Produk lama hilang dari katalog, stoknya pindah ke varian 4mm².
        $this->assertNull(Product::where('slug', 'kabel-pv-dc-solar-1x4mm-hitam-per-meter')->first());
        $variant = $this->parent()->variants()->where('sku', 'KBL-PV-4MM-BLK')->first();
        $this->assertSame(40, (int) $variant->fresh()->stock);
    }

    public function test_rerun_does_not_overwrite_admin_edits(): void
    {
        $this->seed(KabelPvSeeder::class);

        $variant = $this->parent()->variants()->where('sku', 'KBL-PV-6MM-BLK')->first();
        $variant->forceFill(['price' => 25000])->save();

        $this->seed(KabelPvSeeder::class);

        $this->assertSame(25000.0, (float) $variant->fresh()->price);
        $this->assertSame(1, Product::where('slug', 'like', 'kabel-pv-dc-solar-%')->count());
        $this->assertCount(2, $this->parent()->variants);
    }
}
