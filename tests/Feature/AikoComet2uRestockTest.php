<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Database\Seeders\AikoComet2uRestockSeeder;
use Database\Seeders\AikoComet2uSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AIKO Comet 2U: hanya 640 Wp yang dijual — modal terisi (produk & varian),
 * stok 20, margin >= 25%, varian lain dari versi awal dibersihkan.
 */
class AikoComet2uRestockTest extends TestCase
{
    use RefreshDatabase;

    private function variantStock(ProductVariant $variant): int
    {
        return (int) WarehouseStock::where('product_variant_id', $variant->id)->sum('quantity_available');
    }

    public function test_restock_fills_cost_price_and_sets_the_640wp_batch_to_twenty(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(AikoComet2uSeeder::class);
        $this->seed(AikoComet2uRestockSeeder::class);

        $product = Product::where('slug', 'panel-surya-aiko-comet-2u')->firstOrFail();
        $variant = ProductVariant::where('sku', 'AIKO-COMET2U-640')->firstOrFail();

        $this->assertSame('Panel Surya AIKO Comet 2U N-Type ABC 640 Wp', $product->name);
        $this->assertEquals(1_850_000, (float) $product->cost_price);
        $this->assertEquals(1_850_000, (float) $variant->fresh()->cost_price);
        $this->assertTrue((bool) $variant->fresh()->is_active);
        $this->assertSame(20, $this->variantStock($variant));
        $this->assertCount(1, $product->variants);

        // Harga jual 2.490.000 harus memenuhi margin minimal 25% dari revenue.
        $margin = ((float) $variant->price - (float) $product->cost_price) / (float) $variant->price;
        $this->assertGreaterThanOrEqual(0.25, $margin);
    }

    /** Produksi masih punya varian 650 Wp (stok 30) dari versi awal → dibersihkan. */
    public function test_legacy_variants_are_removed_and_their_stock_zeroed(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(AikoComet2uSeeder::class);
        $product = Product::where('slug', 'panel-surya-aiko-comet-2u')->firstOrFail();

        $legacy = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'AIKO-COMET2U-650', 'name' => '650 Wp',
            'option_values' => ['Daya' => '650 Wp'], 'price' => 2_490_000, 'is_active' => true, 'sort_order' => 2,
        ]);
        app(StockService::class)->adjust($product, $legacy, 30, StockMovementType::Purchase);
        $this->assertSame(50, (int) $product->fresh()->stock); // 20 + 30 agregat

        $this->seed(AikoComet2uRestockSeeder::class);

        $this->assertNull(ProductVariant::where('sku', 'AIKO-COMET2U-650')->first());
        $this->assertSame(0, (int) WarehouseStock::where('product_variant_id', $legacy->id)->sum('quantity_available'));
        $this->assertSame(20, (int) $product->fresh()->stock);
        $this->assertCount(1, $product->fresh()->variants);
    }

    /** Menjalankan ulang seeder UTAMA tidak boleh menimpa stok kelolaan admin. */
    public function test_rerunning_the_main_seeder_leaves_admin_managed_stock_alone(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(AikoComet2uSeeder::class);

        $product = Product::where('slug', 'panel-surya-aiko-comet-2u')->firstOrFail();
        $variant = ProductVariant::where('sku', 'AIKO-COMET2U-640')->firstOrFail();

        // Admin menjual 5 keping → stok 15 (batch 20).
        app(StockService::class)->adjust($product, $variant, -5, StockMovementType::Adjustment, note: 'penjualan');
        $this->assertSame(15, $this->variantStock($variant));

        $this->seed(AikoComet2uSeeder::class);

        $this->assertSame(15, $this->variantStock($variant), 'Seeder utama tidak boleh mereset stok.');
    }
}
