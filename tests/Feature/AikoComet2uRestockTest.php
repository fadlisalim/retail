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

/** Batch Agustus 2026 AIKO Comet 2U: modal terisi, stok 20, margin >= 25%. */
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

        $this->assertEquals(1_850_000, (float) $product->cost_price);
        $this->assertTrue((bool) $variant->fresh()->is_active);
        $this->assertSame(20, $this->variantStock($variant));

        // Batch awal 650 Wp tidak boleh ikut tersentuh restock 640.
        $variant650 = ProductVariant::where('sku', 'AIKO-COMET2U-650')->firstOrFail();
        $this->assertSame(30, $this->variantStock($variant650));

        // Harga jual 2.490.000 harus memenuhi margin minimal 25% dari revenue.
        $margin = ((float) $variant->price - (float) $product->cost_price) / (float) $variant->price;
        $this->assertGreaterThanOrEqual(0.25, $margin);
    }

    /** Menjalankan ulang seeder UTAMA tidak boleh menimpa stok kelolaan admin. */
    public function test_rerunning_the_main_seeder_leaves_admin_managed_stock_alone(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(AikoComet2uSeeder::class);

        $product = Product::where('slug', 'panel-surya-aiko-comet-2u')->firstOrFail();
        $variant = ProductVariant::where('sku', 'AIKO-COMET2U-650')->firstOrFail();

        // Admin menjual 5 keping → stok 25 (batch awal 30).
        app(StockService::class)->adjust($product, $variant, -5, StockMovementType::Adjustment, note: 'penjualan');
        $this->assertSame(25, $this->variantStock($variant));

        $this->seed(AikoComet2uSeeder::class);

        $this->assertSame(25, $this->variantStock($variant), 'Seeder utama tidak boleh mereset stok.');
    }
}
