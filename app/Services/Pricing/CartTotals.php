<?php

namespace App\Services\Pricing;

/**
 * The full, server-authoritative money breakdown for a cart or order. Every
 * figure shown to the customer and persisted on the order comes from here.
 */
final class CartTotals
{
    /** @param  LinePrice[]  $lines */
    public function __construct(
        public readonly array $lines,
        public readonly float $itemsSubtotal,
        public readonly float $productDiscount,
        public readonly ?string $couponCode,
        public readonly float $couponDiscount,
        public readonly bool $couponFreeShipping,
        public readonly float $shippingCost,
        public readonly float $packingFee,
        public readonly float $handlingFee,
        public readonly float $insuranceFee,
        public readonly float $taxAmount,
        public readonly float $grandTotal,
        public readonly int $billableWeightGrams,
    ) {
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (LinePrice $l) => $l->quantity, $this->lines));
    }

    public function toArray(): array
    {
        return [
            'items_subtotal' => $this->itemsSubtotal,
            'product_discount' => $this->productDiscount,
            'coupon_code' => $this->couponCode,
            'coupon_discount' => $this->couponDiscount,
            'shipping_cost' => $this->shippingCost,
            'packing_fee' => $this->packingFee,
            'handling_fee' => $this->handlingFee,
            'insurance_fee' => $this->insuranceFee,
            'tax_amount' => $this->taxAmount,
            'grand_total' => $this->grandTotal,
            'billable_weight_grams' => $this->billableWeightGrams,
        ];
    }
}
