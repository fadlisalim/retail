<?php

namespace App\Services\Pricing;

use App\Models\CartItem;

/**
 * Immutable per-line pricing result. Prices here are always computed on the
 * server from the current product/variant, never trusted from the client.
 */
final class LinePrice
{
    public function __construct(
        public readonly CartItem $item,
        public readonly float $unitPrice,
        public readonly float $originalUnitPrice,
        public readonly int $quantity,
        public readonly float $lineTotal,
        public readonly bool $isTaxable,
        public readonly bool $priceIncludesTax,
        public readonly int $lineWeightGrams,
    ) {
    }

    public function promoSaving(): float
    {
        return round(max(0, $this->originalUnitPrice - $this->unitPrice) * $this->quantity, 2);
    }
}
