<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\Shipping\ShippingQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Dropdown status admin vs status pembayaran: memilih "Pembayaran
 * Diverifikasi" (atau status pemenuhan setelahnya) pada pesanan belum lunas
 * harus menjalankan markPaid — bukan sekadar mengganti label, meninggalkan
 * "Belum Dibayar" + tombol Bayar Sekarang di halaman pelanggan.
 */
class OrderStatusPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function unpaidOrder(): Order
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(5, ['price' => 1_650_000]);
        app(CartService::class)->addItem($product, null, 1);

        $quote = new ShippingQuote(
            providerCode: 'JNE', serviceCode: 'REG', label: 'JNE Reguler', type: 'regular',
            cost: 40000, packingFee: 30000, handlingFee: 0, insuranceFee: 0,
            billableWeightGrams: 8000, confirmed: true,
        );

        return app(CheckoutService::class)->place(app(CartService::class)->current()->fresh(), [
            'customer_name' => 'Budi', 'customer_email' => 'budi@test.id', 'customer_phone' => '628111',
            'recipient_name' => 'Budi', 'province' => 'JAWA BARAT', 'city' => 'Bandung',
            'address_line' => 'Jl. Uji 1', 'payment_method' => 'manual_transfer',
            'idempotency_key' => 'sync-'.uniqid(),
        ], $quote);
    }

    public function test_setting_payment_verified_status_actually_marks_the_order_paid(): void
    {
        $order = $this->unpaidOrder();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);

        app(OrderService::class)->changeStatus($order, OrderStatus::PaymentVerified);

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::PaymentVerified, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertEquals($order->grand_total, $order->paid_amount);
    }

    /** Barang tidak boleh jalan tanpa uang masuk: Dikirim/Siap Diambil ditolak. */
    public function test_fulfillment_statuses_are_blocked_while_unpaid(): void
    {
        $order = $this->unpaidOrder();

        foreach ([OrderStatus::Shipped, OrderStatus::ReadyForPickup, OrderStatus::Processing, OrderStatus::Completed] as $target) {
            try {
                app(OrderService::class)->changeStatus($order->fresh(), $target);
                $this->fail('Status '.$target->value.' seharusnya ditolak saat belum dibayar.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('belum dibayar', collect($e->errors())->flatten()->first());
            }
        }

        $order->refresh();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
    }

    /** Setelah dilunaskan (via status Pembayaran Diverifikasi), pengiriman lancar. */
    public function test_after_verification_the_order_can_be_shipped(): void
    {
        $order = $this->unpaidOrder();

        app(OrderService::class)->changeStatus($order, OrderStatus::PaymentVerified);
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Shipped);

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Shipped, $order->status);
    }

    /** Status non-pemenuhan tidak menyentuh pembayaran. */
    public function test_cancelling_an_unpaid_order_does_not_mark_it_paid(): void
    {
        $order = $this->unpaidOrder();

        app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled);

        $order->refresh();
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
    }

    /** Pesanan DP tidak boleh otomatis dianggap lunas penuh. */
    public function test_down_payment_orders_are_not_auto_settled(): void
    {
        $order = $this->unpaidOrder();
        $order->forceFill(['payment_status' => PaymentStatus::DownPaymentPaid])->save();

        app(OrderService::class)->changeStatus($order, OrderStatus::Processing);

        $order->refresh();
        $this->assertSame(PaymentStatus::DownPaymentPaid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->status);
    }
}
