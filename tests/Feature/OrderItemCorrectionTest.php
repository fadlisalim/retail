<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\StockService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Koreksi item pesanan dari halaman pesanan admin (kasus nyata: admin
 * mengedit qty 1 → 8 di "invoice" tapi pesanan tetap 1 → dashboard 1,8jt,
 * invoice 14,4jt, kuitansi kacau). Sekarang koreksi mengubah pesanan itu
 * sendiri: total, pembayaran, stok, komisi, dan dokumen ikut sinkron.
 */
class OrderItemCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    /** Pesanan 1 × 1,8jt yang sudah dibayar: stok terpotong 1 lewat reservasi → commit. */
    private function paidOrderWith(Product $product, int $qty = 1, float $price = 1_800_000, string $status = 'processing', array $extra = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-KOREKSI-'.Str::upper(Str::random(4)),
            'public_token' => Str::uuid(),
            'customer_name' => 'Pak Andi', 'customer_email' => 'andi@test.id', 'customer_phone' => '0812',
            'status' => OrderStatus::AwaitingPayment->value,
            'payment_status' => PaymentStatus::Unpaid->value,
            'items_subtotal' => $qty * $price, 'tax_amount' => 0, 'shipping_cost' => 50_000, 'grand_total' => $qty * $price + 50_000,
        ], $extra));
        $order->items()->create([
            'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
            'unit_price' => $price, 'original_unit_price' => $price, 'quantity' => $qty, 'line_total' => $qty * $price, 'weight_grams' => 1000,
        ]);
        $order->payments()->create(['method' => 'manual_transfer', 'status' => PaymentStatus::Unpaid->value, 'amount' => $order->grand_total]);

        app(StockService::class)->reserveForOrder($order);
        $order = app(OrderService::class)->markPaid($order->fresh());
        app(InvoiceService::class)->createForOrder($order);
        $order->update(['status' => $status]);

        return $order->fresh(['items', 'invoice']);
    }

    public function test_quantity_correction_updates_totals_payment_stock_and_documents(): void
    {
        $product = $this->stockedProduct(10, ['name' => 'Jinko 605 Wp', 'price' => 1_800_000]);
        $order = $this->paidOrderWith($product);
        $this->assertEquals(9, $product->fresh()->stock);

        $this->actingAs($this->staff('admin-sales'))
            ->put(route('admin.orders.invoice.update', $order), [
                'name' => 'Pak Andi',
                'items' => [['id' => $order->items->first()->id, 'name' => 'Jinko 605 Wp', 'quantity' => 8, 'unit_price' => 1_800_000]],
            ])->assertRedirect()->assertSessionHas('success');

        $order = $order->fresh(['items', 'invoice', 'payments']);
        $this->assertEquals(8, $order->items->first()->quantity);
        $this->assertEquals(14_400_000, (float) $order->items_subtotal);
        $this->assertEquals(14_450_000, (float) $order->grand_total);
        $this->assertEquals(14_450_000, (float) $order->paid_amount);
        $this->assertEquals(14_450_000, (float) $order->payments->first()->amount_paid);

        // Stok: 7 unit tambahan keluar dari gudang, tercatat sebagai penjualan.
        $this->assertEquals(2, $product->fresh()->stock);
        $this->assertEquals(8, $product->fresh()->sold_count);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity' => -7, 'reference_id' => $order->id]);

        // Invoice & kuitansi mengikuti pesanan.
        $this->assertNull($order->invoice->items_snapshot);
        $this->assertEquals(14_400_000, (float) $order->invoice->subtotal);
        $this->assertEquals(14_450_000, (float) $order->invoice->total);
        $this->assertSame(8, $order->invoice->lineItems()[0]['quantity']);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id]);
        $this->assertStringContainsString('Koreksi item pesanan', $order->statusHistories()->latest('id')->first()->internal_note);
    }

    public function test_reducing_quantity_returns_stock_to_the_warehouse(): void
    {
        $product = $this->stockedProduct(10, ['price' => 1_800_000]);
        $order = $this->paidOrderWith($product, qty: 5);
        $this->assertEquals(5, $product->fresh()->stock);

        app(OrderService::class)->correctItems($order, [
            ['id' => $order->items->first()->id, 'name' => $product->name, 'quantity' => 2, 'unit_price' => 1_800_000],
        ]);

        $this->assertEquals(8, $product->fresh()->stock);
        $this->assertEquals(2, $product->fresh()->sold_count);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'return', 'quantity' => 3]);
        $this->assertEquals(3_650_000, (float) $order->fresh()->grand_total);
    }

    public function test_insufficient_stock_shows_an_error_and_changes_nothing(): void
    {
        $product = $this->stockedProduct(3, ['price' => 1_800_000]);
        $order = $this->paidOrderWith($product);

        $this->actingAs($this->staff('admin-sales'))
            ->put(route('admin.orders.invoice.update', $order), [
                'name' => 'Pak Andi',
                'items' => [['id' => $order->items->first()->id, 'name' => $product->name, 'quantity' => 8, 'unit_price' => 1_800_000]],
            ])->assertRedirect()->assertSessionHasErrors('items');

        $this->assertEquals(1, $order->fresh()->items->first()->quantity);
        $this->assertEquals(1_850_000, (float) $order->fresh()->grand_total);
        $this->assertEquals(2, $product->fresh()->stock);
    }

    public function test_super_admin_can_correct_a_completed_order_but_sales_cannot(): void
    {
        $product = $this->stockedProduct(10, ['price' => 1_800_000]);
        $order = $this->paidOrderWith($product, status: OrderStatus::Completed->value);
        $payload = [
            'name' => 'Pak Andi',
            'items' => [['id' => $order->items->first()->id, 'name' => $product->name, 'quantity' => 8, 'unit_price' => 1_800_000]],
        ];

        $this->actingAs($this->staff('admin-sales'))
            ->put(route('admin.orders.invoice.update', $order), $payload)
            ->assertSessionHasErrors('invoice');
        $this->assertEquals(1, $order->fresh()->items->first()->quantity);

        $super = $this->staff('super-admin');
        $this->actingAs($super)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Edit Data Dokumen')
            ->assertSee('Sebagai Super Admin');

        $this->actingAs($super)
            ->put(route('admin.orders.invoice.update', $order), $payload)
            ->assertRedirect()->assertSessionHas('success');
        $this->assertEquals(8, $order->fresh()->items->first()->quantity);
        $this->assertEquals(14_450_000, (float) $order->fresh()->grand_total);
    }

    public function test_affiliate_commission_follows_the_corrected_line_total(): void
    {
        $product = $this->stockedProduct(10, ['price' => 1_800_000, 'affiliate_rate' => 5]);
        $affiliateUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id, 'code' => 'AFFX1', 'status' => AffiliateStatus::Active, 'full_name' => 'Afiliator',
            'bank_name' => 'BCA', 'bank_account_number' => '1', 'bank_account_holder' => 'Afiliator', 'verified_at' => now(),
        ]);
        $order = $this->paidOrderWith($product, extra: ['affiliate_id' => $affiliate->id, 'affiliate_source' => 'referral']);
        app(AffiliateService::class)->recordCommissions($order);
        $this->assertEquals(90_000, (float) $order->affiliateCommissions()->first()->amount);

        app(OrderService::class)->correctItems($order, [
            ['id' => $order->items->first()->id, 'name' => $product->name, 'quantity' => 8, 'unit_price' => 1_800_000],
        ]);

        $commission = $order->affiliateCommissions()->first();
        $this->assertEquals(14_400_000, (float) $commission->base_amount);
        $this->assertEquals(720_000, (float) $commission->amount);
        $this->assertSame(CommissionStatus::Pending, $commission->status);
    }
}
