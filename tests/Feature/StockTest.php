<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\WarehouseStock;
use App\Services\CartService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_cannot_go_negative(): void
    {
        $product = $this->stockedProduct(5);

        $this->expectException(RuntimeException::class);
        app(StockService::class)->adjust($product, null, -10, StockMovementType::Adjustment);
    }

    public function test_cart_rejects_quantity_above_stock(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(3);

        $this->expectException(ValidationException::class);
        app(CartService::class)->addItem($product, null, 5);
    }

    public function test_adjustment_is_recorded_in_the_ledger(): void
    {
        $product = $this->stockedProduct(10);

        app(StockService::class)->adjust($product, null, 5, StockMovementType::Purchase, note: 'Restock');

        $this->assertEquals(15, $product->fresh()->stock);
        $this->assertEquals(15, WarehouseStock::where('product_id', $product->id)->first()->quantity_available);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'type' => 'purchase', 'quantity' => 5,
        ]);
    }
}
