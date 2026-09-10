<?php

namespace App\Services\Shipping;

/**
 * Everything a provider needs to price a shipment, derived server-side from the
 * cart + destination. Providers must not read the request directly.
 */
final class ShippingContext
{
    public function __construct(
        public readonly int $totalActualGrams,
        public readonly float $totalVolumeCm3,
        public readonly float $subtotal,
        public readonly string $destinationProvince,
        public readonly bool $hasFreightItem,
        public readonly bool $hasPickupOnlyItem,
        public readonly int $packageCount = 1,
        public readonly ?string $destinationCity = null,
        // Weight of only the items heavy enough to need wooden-crate packing.
        public readonly int $packableActualGrams = 0,
        public readonly float $packableVolumeCm3 = 0.0,
    ) {}
}
