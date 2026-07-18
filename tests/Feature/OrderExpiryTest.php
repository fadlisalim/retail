<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
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

    public function test_unpaid_order_past_window_is_auto_cancelled(): void
    {
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Cancelled, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_recent_unpaid_order_is_kept(): void
    {
        $order = $this->order();
        $order->forceFill(['created_at' => now()->subHours(2)])->save();

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
