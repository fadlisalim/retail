<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Shipping\ShippingQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into an order. Critical guarantees:
 *  - Every price is RECOMPUTED here from live product data (CartCalculator); the
 *    client's numbers are never trusted.
 *  - The whole thing runs in one DB transaction.
 *  - An idempotency key makes a double-clicked "Bayar" return the same order
 *    instead of creating a duplicate.
 *  - Stock is reserved (not yet sold) so two buyers can't claim the last unit.
 */
class CheckoutService
{
    public function __construct(
        private readonly CartCalculator $calculator,
        private readonly StockService $stock,
        private readonly InvoiceService $invoices,
        private readonly SettingService $settings,
    ) {
    }

    public function place(Cart $cart, array $data, ShippingQuote $shipping): Order
    {
        // Idempotency: return the existing order for a repeated submit.
        if (! empty($data['idempotency_key'])) {
            $existing = Order::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $buyable = $this->calculator->buyableItems($cart);
        if ($buyable->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Tidak ada produk yang dapat di-checkout.']);
        }

        // Require condition acknowledgement for open-box / used items.
        foreach ($buyable as $item) {
            if ($item->product->requiresConditionAck() && ! $item->condition_acknowledged) {
                throw ValidationException::withMessages([
                    'condition' => "Anda harus menyetujui kondisi produk: {$item->product->name}.",
                ]);
            }
        }

        // SERVER-SIDE recompute with the chosen shipping quote.
        $totals = $this->calculator->calculate($cart, $shipping);

        return DB::transaction(function () use ($cart, $data, $shipping, $totals, $buyable) {
            $shippingConfirmed = $shipping->confirmed;

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'public_token' => (string) Str::uuid(),
                'user_id' => $cart->user_id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => $shippingConfirmed ? OrderStatus::AwaitingPayment : OrderStatus::AwaitingShippingConfirmation,
                'payment_status' => PaymentStatus::Unpaid,
                'items_subtotal' => $totals->itemsSubtotal,
                'product_discount' => $totals->productDiscount,
                'coupon_discount' => $totals->couponDiscount,
                'coupon_code' => $totals->couponCode,
                'shipping_cost' => $totals->shippingCost,
                'packing_fee' => $totals->packingFee,
                'handling_fee' => $totals->handlingFee,
                'insurance_fee' => $totals->insuranceFee,
                'tax_amount' => $totals->taxAmount,
                'grand_total' => $totals->grandTotal,
                'shipping_cost_confirmed' => $shippingConfirmed,
                'shipping_method' => $shipping->providerCode.'/'.$shipping->serviceCode,
                'shipping_service_name' => $shipping->label,
                'billable_weight_grams' => $totals->billableWeightGrams,
                'payment_method' => $data['payment_method'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($totals->lines as $line) {
                $item = $line->item;
                $product = $item->product;
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->variant?->sku ?? $product->sku,
                    'name' => $product->name.($item->variant ? ' - '.$item->variant->name : ''),
                    'variant_options' => $item->variant?->option_values,
                    'unit_price' => $line->unitPrice,
                    'original_unit_price' => $line->originalUnitPrice,
                    'quantity' => $line->quantity,
                    'discount_amount' => $line->promoSaving(),
                    'tax_amount' => 0,
                    'line_total' => $line->lineTotal,
                    'weight_grams' => $line->lineWeightGrams,
                    'is_taxable' => $line->isTaxable,
                ]);
            }

            $order->addresses()->create([
                'type' => 'shipping',
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['recipient_phone'] ?? $data['customer_phone'] ?? '',
                'company_name' => $data['company_name'] ?? null,
                'npwp' => $data['npwp'] ?? null,
                'province' => $data['province'],
                'city' => $data['city'],
                'district' => $data['district'] ?? null,
                'subdistrict' => $data['subdistrict'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'address_line' => $data['address_line'],
                'landmark' => $data['landmark'] ?? null,
            ]);

            $order->statusHistories()->create([
                'status' => $order->status->value,
                'changed_by' => $cart->user_id,
                'customer_note' => 'Pesanan dibuat.',
            ]);

            // Reserve stock immediately so the sale is protected while awaiting payment.
            $this->stock->reserveForOrder($order, (int) $this->settings->get('shipping.reservation_minutes', 30));

            // Pending payment record (adapter fills in VA/QR later).
            Payment::create([
                'order_id' => $order->id,
                'method' => $data['payment_method'] ?? 'manual_transfer',
                'status' => PaymentStatus::Unpaid->value,
                'amount' => $order->grand_total,
                'expires_at' => now()->addDay(),
            ]);

            // Record coupon usage server-side.
            if ($totals->couponCode && $totals->couponDiscount > 0) {
                $coupon = \App\Models\Coupon::whereRaw('LOWER(code) = ?', [mb_strtolower($totals->couponCode)])->first();
                if ($coupon) {
                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'user_id' => $cart->user_id,
                        'order_id' => $order->id,
                        'discount_amount' => $totals->couponDiscount,
                    ]);
                    $coupon->increment('used_count');
                }
            }

            $this->invoices->createForOrder($order);

            // Empty the buyable items from the cart (quotation items remain).
            $cart->allItems()
                ->whereIn('id', $buyable->pluck('id'))
                ->delete();
            $cart->update(['coupon_code' => null]);

            return $order->fresh(['items', 'shippingAddress', 'payments', 'invoice']);
        });
    }

    /**
     * Order number: readable prefix + random suffix so it is not sequentially
     * enumerable. Public tracking always uses the UUID token instead.
     */
    private function generateOrderNumber(): string
    {
        do {
            $number = 'RS-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
