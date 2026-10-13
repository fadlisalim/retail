<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(6)),
            'public_token' => Str::uuid(),
            'customer_name' => 'Uji',
            'customer_email' => 'uji@test.id',
            'status' => OrderStatus::AwaitingPayment->value,
            'payment_status' => PaymentStatus::Unpaid->value,
        ], $overrides));
    }

    public function test_unpaid_order_past_window_is_auto_cancelled_and_can_be_restored(): void
    {
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subDays(8)])->save();
        $manual = $this->order(['customer_name' => 'Batal Manual']);
        $manual->forceFill(['created_at' => now()->subDays(8)])->save();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Cancelled, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);

        // Pesanan yang dibatalkan admin secara manual bukan sasaran pemulihan massal.
        $manual->forceFill(['status' => OrderStatus::AwaitingPayment->value, 'cancelled_at' => null])->save();
        $manual->statusHistories()->delete();
        app(OrderService::class)->cancel($manual, null, 'Customer minta batal.');

        $this->artisan('orders:pulihkan', ['--dry-run' => true])->expectsOutputToContain('1 dari 1 pesanan auto-batal')->assertSuccessful();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->artisan('orders:pulihkan', ['--sejak' => 48])->expectsOutputToContain('Dipulihkan: '.$order->order_number)->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::AwaitingPayment, $fresh->status);
        $this->assertNull($fresh->cancelled_at);
        $this->assertSame('Pesanan diaktifkan kembali, menunggu pembayaran.', $fresh->statusHistories()->reorder()->latest('id')->first()->customer_note);
        $this->assertSame(OrderStatus::Cancelled, $manual->fresh()->status);

        // Pulihkan dua kali → ditolak (bukan lagi Dibatalkan).
        $this->artisan('orders:pulihkan')->expectsOutputToContain('0 dari 0')->assertSuccessful();
    }

    public function test_admin_restore_button_reserves_stock_and_rejects_non_cancelled(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());
        $product = $this->stockedProduct(5);

        $order = $this->order(['status' => OrderStatus::Cancelled->value, 'cancelled_at' => now()]);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Pulihkan pesanan');
        $this->actingAs($admin)->post(route('admin.orders.restore', $order))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        $this->assertSame(1, $order->reservations()->where('status', 'active')->count());
        $this->assertEquals(3, $product->fresh()->stock); // 5 − 2 dipesan ulang

        $this->actingAs($admin)->post(route('admin.orders.restore', $order))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Pulihkan pesanan');
    }

    public function test_recent_unpaid_order_is_kept(): void
    {
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subDays(6)])->save(); // default 7×24 jam

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_order_under_verification_is_not_cancelled(): void
    {
        // Proof uploaded (awaiting_verification) → a human must decide, never auto-cancel.
        $order = $this->order(['payment_status' => PaymentStatus::AwaitingVerification->value]);
        $order->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_disabled_when_window_is_zero(): void
    {
        config(['rekasurya.orders.payment_window_hours' => 0]);
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }
}
