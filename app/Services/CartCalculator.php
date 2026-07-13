<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Pricing\CartTotals;
use App\Services\Pricing\LinePrice;
use App\Services\Shipping\ShippingQuote;

/**
 * The single source of truth for cart/order money. Given a cart (and optionally a
 * chosen shipping quote) it recomputes every figure from the live product data.
 * Checkout calls this again server-side right before creating the order, so a
 * tampered client price can never take effect.
 */
class CartCalculator
{
    public function __construct(
        private readonly TaxService $tax,
        private readonly CouponService $coupons,
    ) {
    }

    public function calculate(Cart $cart, ?ShippingQuote $shipping = null): CartTotals
    {
        $lines = [];
        $itemsSubtotal = 0.0;
        $productDiscount = 0.0;
        $exclusiveTaxable = 0.0;
        $inclusiveTaxable = 0.0;
        $billableWeight = 0;

        foreach ($this->buyableItems($cart) as $item) {
            $line = $this->lineFor($item);
            $lines[] = $line;

            $itemsSubtotal += $line->lineTotal;
            $productDiscount += $line->promoSaving();
            $billableWeight += $line->lineWeightGrams;

            if ($line->isTaxable) {
                $line->priceIncludesTax
                    ? $inclusiveTaxable += $line->lineTotal
                    : $exclusiveTaxable += $line->lineTotal;
            }
        }

        $itemsSubtotal = round($itemsSubtotal, 2);

        // --- Coupon (validated server-side) ---
        $couponResult = $this->coupons->evaluate($cart->coupon_code, $itemsSubtotal, $cart->user_id);
        $couponDiscount = $couponResult['discount'];
        $couponFreeShipping = $couponResult['free_shipping'];
        $couponCode = $couponResult['coupon']?->code;

        // --- Shipping (from the chosen quote) ---
        $shippingCost = $shipping?->cost ?? 0.0;
        $packingFee = $shipping?->packingFee ?? 0.0;
        $handlingFee = $shipping?->handlingFee ?? 0.0;
        $insuranceFee = $shipping?->insuranceFee ?? 0.0;
        if ($couponFreeShipping) {
            $shippingCost = 0.0;
        }
        if ($shipping) {
            $billableWeight = $shipping->billableWeightGrams;
        }

        // --- Tax: reduce the taxable base proportionally by the coupon discount ---
        $discountFactor = $itemsSubtotal > 0 ? max(0, ($itemsSubtotal - $couponDiscount) / $itemsSubtotal) : 0;
        $taxAdded = $this->tax->taxOnExclusive($exclusiveTaxable * $discountFactor);
        $taxEmbedded = $this->tax->taxWithinInclusive($inclusiveTaxable * $discountFactor);
        $taxAmount = round($taxAdded + $taxEmbedded, 2);

        // Embedded tax is already inside the subtotal, so only $taxAdded is new money.
        $grandTotal = round(
            $itemsSubtotal - $couponDiscount
            + $shippingCost + $packingFee + $handlingFee + $insuranceFee
            + $taxAdded,
            2,
        );

        return new CartTotals(
            lines: $lines,
            itemsSubtotal: $itemsSubtotal,
            productDiscount: round($productDiscount, 2),
            couponCode: $couponCode,
            couponDiscount: $couponDiscount,
            couponFreeShipping: $couponFreeShipping,
            shippingCost: $shippingCost,
            packingFee: $packingFee,
            handlingFee: $handlingFee,
            insuranceFee: $insuranceFee,
            taxAmount: $taxAmount,
            grandTotal: max(0, $grandTotal),
            billableWeightGrams: $billableWeight,
        );
    }

    /** Cart items that go through checkout (quotation-only products are excluded). */
    public function buyableItems(Cart $cart): \Illuminate\Support\Collection
    {
        return $cart->items->filter(
            fn (CartItem $item) => $item->product && ! $item->product->requires_quotation
        )->values();
    }

    private function lineFor(CartItem $item): LinePrice
    {
        $product = $item->product;
        $variant = $item->variant;

        $unit = $variant ? $variant->effectivePrice() : $product->effectivePrice();
        $original = $variant
            ? $variant->effectiveBasePrice()
            : (float) $product->price;

        $qty = max(1, (int) $item->quantity);
        $unitWeight = $variant ? $variant->weightGrams() : (int) $product->weight_grams;

        return new LinePrice(
            item: $item,
            unitPrice: round($unit, 2),
            originalUnitPrice: round($original, 2),
            quantity: $qty,
            lineTotal: round($unit * $qty, 2),
            isTaxable: (bool) $product->is_taxable,
            priceIncludesTax: (bool) $product->price_includes_tax,
            lineWeightGrams: $unitWeight * $qty,
        );
    }
}
