<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nomor rekening (setting payment.bank_account) tampil di invoice selama
 * tagihan belum lunas, dan hilang setelah pembayaran diterima — invoice lunas
 * tidak butuh instruksi transfer.
 */
class InvoiceBankAccountTest extends TestCase
{
    use RefreshDatabase;

    private const BANK = "BNI 3355113351 a.n. REKASURYA CIPTA DAYA CV\nBRI 040701000954302 a.n. REKASURYA CIPTA DAYA CV";

    private function orderWithInvoice(PaymentStatus $paymentStatus): Order
    {
        app(SettingService::class)->set('payment.bank_account', self::BANK, 'string', 'payment');

        $order = Order::create([
            'order_number' => 'ORD-BANK-'.Str::upper(Str::random(4)),
            'public_token' => Str::uuid(),
            'customer_name' => 'Budi Transfer',
            'customer_email' => 'budi@test.id',
            'customer_phone' => '08123',
            'status' => 'awaiting_payment',
            'payment_status' => $paymentStatus->value,
            'items_subtotal' => 5_000_000,
            'tax_amount' => 0,
            'grand_total' => 5_000_000,
        ]);
        app(InvoiceService::class)->createForOrder($order);

        return $order->fresh('invoice');
    }

    public function test_an_unpaid_invoice_shows_the_bank_accounts(): void
    {
        $order = $this->orderWithInvoice(PaymentStatus::Unpaid);

        $this->get(route('invoices.show', $order->invoice->public_token))
            ->assertOk()
            ->assertSee('Pembayaran Transfer Ke')
            ->assertSee('BNI 3355113351')
            ->assertSee('BRI 040701000954302');
    }

    /** DP masuk tapi belum lunas — rekening tetap perlu tampil untuk pelunasan. */
    public function test_a_down_payment_invoice_still_shows_the_bank_accounts(): void
    {
        $order = $this->orderWithInvoice(PaymentStatus::DownPaymentPaid);

        $this->get(route('invoices.show', $order->invoice->public_token))
            ->assertOk()
            ->assertSee('Pembayaran Transfer Ke')
            ->assertSee('BNI 3355113351');
    }

    public function test_a_paid_invoice_hides_the_bank_accounts(): void
    {
        $order = $this->orderWithInvoice(PaymentStatus::Paid);

        $this->get(route('invoices.show', $order->invoice->public_token))
            ->assertOk()
            ->assertDontSee('Pembayaran Transfer Ke')
            ->assertDontSee('BNI 3355113351');
    }

    /** Tanpa setting rekening, blok pembayaran tidak dirender sama sekali. */
    public function test_no_block_is_rendered_when_the_setting_is_empty(): void
    {
        $order = $this->orderWithInvoice(PaymentStatus::Unpaid);
        app(SettingService::class)->set('payment.bank_account', '', 'string', 'payment');

        $this->get(route('invoices.show', $order->invoice->public_token))
            ->assertOk()
            ->assertDontSee('Pembayaran Transfer Ke');
    }
}
