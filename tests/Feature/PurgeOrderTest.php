<?php

namespace Tests\Feature;

use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\WarehouseStock;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\Shipping\ShippingQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * order:purge — hapus pesanan uji BERSIH: stok & sold_count kembali, komisi
 * ikut lenyap; komisi yang sudah dibayarkan memblokir penghapusan.
 */
class PurgeOrderTest extends TestCase
{
    use RefreshDatabase;

    private function placedOrder(int $qty = 1): Order
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(10, ['price' => 1_650_000]);
        app(CartService::class)->addItem($product, null, $qty);

        $quote = new ShippingQuote(
            providerCode: 'JNE', serviceCode: 'REG', label: 'JNE Reguler', type: 'regular',
            cost: 40000, packingFee: 0, handlingFee: 0, insuranceFee: 0,
            billableWeightGrams: 8000, confirmed: true,
        );

        return app(CheckoutService::class)->place(app(CartService::class)->current()->fresh(), [
            'customer_name' => 'Dummy', 'customer_email' => 'dummy@test.id', 'customer_phone' => '628',
            'recipient_name' => 'Dummy', 'province' => 'JAWA BARAT', 'city' => 'Bandung',
            'address_line' => 'Jl. Uji', 'payment_method' => 'manual_transfer',
            'idempotency_key' => 'purge-'.uniqid(),
        ], $quote);
    }

    public function test_a_completed_order_is_purged_with_stock_and_sold_count_restored(): void
    {
        $order = $this->placedOrder(2);
        app(OrderService::class)->markPaid($order); // lunas + commit stok
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Completed);
        $product = $order->items->first()->product;

        $this->assertSame(2, (int) $product->fresh()->sold_count);
        $this->assertSame(8, (int) WarehouseStock::where('product_id', $product->id)->sum('quantity_available'));

        $this->artisan('order:purge', ['order_numbers' => [$order->order_number], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
        $this->assertDatabaseMissing('invoices', ['order_id' => $order->id]);
        $this->assertSame(0, (int) $product->fresh()->sold_count);
        $this->assertSame(10, (int) WarehouseStock::where('product_id', $product->id)->sum('quantity_available'));
    }

    public function test_an_unpaid_order_releases_its_reservation(): void
    {
        $order = $this->placedOrder(3);
        $product = $order->items->first()->product;
        $this->assertSame(3, (int) WarehouseStock::where('product_id', $product->id)->sum('quantity_reserved'));

        $this->artisan('order:purge', ['order_numbers' => [$order->order_number], '--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertSame(0, (int) WarehouseStock::where('product_id', $product->id)->sum('quantity_reserved'));
        $this->assertSame(10, (int) WarehouseStock::where('product_id', $product->id)->sum('quantity_available'));
    }

    public function test_orders_with_paid_out_commissions_are_refused(): void
    {
        $order = $this->placedOrder();
        $affiliateUser = $this->customer();
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id, 'code' => 'PRG001', 'status' => 'active',
            'full_name' => 'A', 'id_number' => '1', 'phone' => '08', 'address' => 'Jkt', 'channel' => 'IG',
        ]);
        $affiliate->commissions()->create([
            'order_id' => $order->id, 'base_amount' => 1_650_000, 'rate' => 2.5, 'amount' => 41250,
            'status' => CommissionStatus::Paid->value,
        ]);

        $this->artisan('order:purge', ['order_numbers' => [$order->order_number], '--force' => true])
            ->assertSuccessful();

        // Ditolak: pesanan tetap ada karena komisinya sudah dibayarkan.
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
