<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $invoices = app(InvoiceService::class);
        $customers = User::where('is_staff', false)->get();
        $products = Product::where('is_purchasable', true)->where('requires_quotation', false)->inStock()->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        $statuses = [
            [OrderStatus::Completed, PaymentStatus::Paid],
            [OrderStatus::Completed, PaymentStatus::Paid],
            [OrderStatus::Completed, PaymentStatus::Paid],
            [OrderStatus::Shipped, PaymentStatus::Paid],
            [OrderStatus::Processing, PaymentStatus::Paid],
            [OrderStatus::PaymentVerified, PaymentStatus::Paid],
            [OrderStatus::AwaitingPayment, PaymentStatus::Unpaid],
            [OrderStatus::AwaitingShippingConfirmation, PaymentStatus::Unpaid],
            [OrderStatus::Cancelled, PaymentStatus::Failed],
            [OrderStatus::Packing, PaymentStatus::Paid],
        ];

        foreach ($statuses as $index => [$status, $paymentStatus]) {
            $customer = $customers[$index % $customers->count()];
            $lineProducts = $products->random(min(2, $products->count()));

            $subtotal = 0;
            $items = [];
            foreach ($lineProducts as $product) {
                $qty = rand(1, 2);
                $price = $product->effectivePrice();
                $subtotal += $price * $qty;
                $items[] = [$product, $qty, $price];
            }

            $shipping = 150000;
            $tax = round($subtotal * 0.11);
            $grandTotal = $subtotal + $shipping + $tax;

            $order = Order::create([
                'order_number' => 'RS-'.now()->subDays(30 - $index * 2)->format('ymd').'-'.strtoupper(Str::random(5)),
                'public_token' => (string) Str::uuid(),
                'user_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->whatsapp,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'items_subtotal' => $subtotal,
                'shipping_cost' => $shipping,
                'tax_amount' => $tax,
                'grand_total' => $grandTotal,
                'paid_amount' => $paymentStatus === PaymentStatus::Paid ? $grandTotal : 0,
                'shipping_cost_confirmed' => $status !== OrderStatus::AwaitingShippingConfirmation,
                'shipping_service_name' => 'JNE — Reguler',
                'billable_weight_grams' => 5000,
                'payment_method' => 'manual_transfer',
                'paid_at' => $paymentStatus === PaymentStatus::Paid ? now()->subDays(29 - $index * 2) : null,
                'completed_at' => $status === OrderStatus::Completed ? now()->subDays(20 - $index) : null,
                'created_at' => now()->subDays(30 - $index * 2),
            ]);

            foreach ($items as [$product, $qty, $price]) {
                $order->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit_price' => $price,
                    'original_unit_price' => $product->price,
                    'quantity' => $qty,
                    'line_total' => $price * $qty,
                    'weight_grams' => $product->weight_grams * $qty,
                    'is_taxable' => true,
                ]);
                if ($status === OrderStatus::Completed) {
                    $product->increment('sold_count', $qty);
                }
            }

            $address = $customer->defaultAddress;
            $order->addresses()->create([
                'type' => 'shipping',
                'recipient_name' => $customer->name,
                'phone' => $customer->whatsapp,
                'province' => $address?->province ?? 'DKI Jakarta',
                'city' => $address?->city ?? 'Jakarta',
                'address_line' => $address?->address_line ?? 'Jl. Contoh No. 1',
            ]);

            $order->statusHistories()->create([
                'status' => $status->value,
                'customer_note' => $status->label(),
                'created_at' => $order->created_at,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'method' => 'manual_transfer',
                'status' => $paymentStatus->value,
                'amount' => $grandTotal,
                'amount_paid' => $paymentStatus === PaymentStatus::Paid ? $grandTotal : 0,
                'paid_at' => $paymentStatus === PaymentStatus::Paid ? $order->paid_at : null,
            ]);

            if ($paymentStatus !== PaymentStatus::Failed) {
                $invoices->createForOrder($order);
            }
        }
    }
}
