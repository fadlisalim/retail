<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Role;
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

    public function test_admin_can_add_subtract_and_set_stock(): void
    {
        $this->actingAs($this->stockStaff());
        $product = $this->stockedProduct(10);

        // Add
        $this->post(route('admin.stock.adjust', $product), ['mode' => 'add', 'amount' => 5, 'type' => 'purchase'])->assertRedirect();
        $this->assertEquals(15, $product->fresh()->stock);

        // Subtract
        $this->post(route('admin.stock.adjust', $product), ['mode' => 'subtract', 'amount' => 3, 'type' => 'adjustment'])->assertRedirect();
        $this->assertEquals(12, $product->fresh()->stock);

        // Set to total
        $this->post(route('admin.stock.adjust', $product), ['mode' => 'set', 'amount' => 50, 'type' => 'adjustment'])->assertRedirect();
        $this->assertEquals(50, $product->fresh()->stock);
    }

    public function test_no_change_is_reported_not_errored_out(): void
    {
        $this->actingAs($this->stockStaff());
        $product = $this->stockedProduct(10);

        $this->post(route('admin.stock.adjust', $product), ['mode' => 'set', 'amount' => 10, 'type' => 'adjustment'])
            ->assertSessionHas('error');
        $this->assertEquals(10, $product->fresh()->stock);
    }
}
