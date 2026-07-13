<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function createForOrder(Order $order): Invoice
    {
        if ($order->invoice) {
            return $order->invoice;
        }

        return Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $this->generateNumber(),
            'public_token' => (string) Str::uuid(),
            'subtotal' => $order->items_subtotal,
            'discount' => (float) $order->product_discount + (float) $order->coupon_discount,
            'shipping' => (float) $order->shipping_cost + (float) $order->packing_fee + (float) $order->handling_fee + (float) $order->insurance_fee,
            'tax' => $order->tax_amount,
            'total' => $order->grand_total,
            'company_snapshot' => $this->settings->company(),
            'customer_snapshot' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'address' => $order->shippingAddress?->fullAddress(),
                'npwp' => $order->shippingAddress?->npwp,
                'company' => $order->shippingAddress?->company_name,
            ],
            'issued_at' => now(),
        ]);
    }

    /**
     * Human-readable but not sequentially guessable: INV/YYYY/MM/<random>.
     * The public invoice URL uses the UUID token, never this number.
     */
    private function generateNumber(): string
    {
        return sprintf('INV/%s/%s', now()->format('Y/m'), strtoupper(Str::random(6)));
    }
}
