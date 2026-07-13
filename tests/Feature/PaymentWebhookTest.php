<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithPayment(): Payment
    {
        $order = Order::create([
            'order_number' => 'RS-TEST-1', 'public_token' => (string) Str::uuid(),
            'customer_name' => 'Budi', 'customer_email' => 'b@test.id',
            'status' => OrderStatus::AwaitingPayment, 'payment_status' => PaymentStatus::Unpaid,
            'items_subtotal' => 1000000, 'grand_total' => 1110000,
        ]);

        return Payment::create([
            'order_id' => $order->id, 'method' => 'va_demo', 'status' => PaymentStatus::AwaitingVerification->value,
            'amount' => 1110000, 'external_id' => 'EXT-123',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.demo_gateway.secret' => 'topsecret']);
        $payment = $this->makeOrderWithPayment();

        $payload = ['status' => 'PAID', 'external_id' => 'EXT-123', 'event_id' => 'evt-1'];
        $result = app(PaymentManager::class)->handleWebhook('va_demo', $payload, json_encode($payload), 'wrong-signature');

        $this->assertSame('invalid_signature', $result['status']);
        $this->assertEquals(PaymentStatus::Unpaid, $payment->order->fresh()->payment_status);
    }

    public function test_valid_signature_marks_order_paid_and_is_idempotent(): void
    {
        config(['services.demo_gateway.secret' => 'topsecret']);
        $payment = $this->makeOrderWithPayment();

        $payload = ['status' => 'PAID', 'external_id' => 'EXT-123', 'event_id' => 'evt-1'];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $raw, 'topsecret');

        $manager = app(PaymentManager::class);
        $first = $manager->handleWebhook('va_demo', $payload, $raw, $signature);
        $second = $manager->handleWebhook('va_demo', $payload, $raw, $signature); // replay

        $this->assertSame('processed', $first['status']);
        $this->assertSame('duplicate', $second['status']); // replay rejected
        $this->assertEquals(PaymentStatus::Paid, $payment->order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_webhook_logs', 1);
    }
}
