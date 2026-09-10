<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarehouseStock;
use App\Services\CartService;
use App\Services\StockService;
use Database\Seeders\RoleSeeder;
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

    private function stockStaff(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-gudang')->first());

        return $user;
    }

    /** Halaman Stok lama dihapus — admin gudang mengatur stok dari Edit Cepat Produk. */
    public function test_warehouse_staff_sets_stock_from_the_quick_edit_page(): void
    {
        $this->actingAs($this->stockStaff());
        $product = $this->stockedProduct(10);

        $this->get(route('admin.prices.index'))->assertOk()->assertSee('Edit Cepat Produk');

        $this->patchJson(route('admin.prices.update', $product), ['stock' => 50])
            ->assertOk()->assertJson(['stok' => 50]);
        $this->assertEquals(50, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => 40]);

        $this->patchJson(route('admin.prices.update', $product), ['stock' => 12])
            ->assertOk()->assertJson(['stok' => 12]);
        $this->assertEquals(12, $product->fresh()->stock);
    }

    public function test_setting_the_same_stock_records_no_movement(): void
    {
        $this->actingAs($this->stockStaff());
        $product = $this->stockedProduct(10);
        $movements = StockMovement::where('product_id', $product->id)->count();

        $this->patchJson(route('admin.prices.update', $product), ['stock' => 10])->assertOk();

        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertSame($movements, StockMovement::where('product_id', $product->id)->count());
    }
}
