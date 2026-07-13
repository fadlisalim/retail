<?php

namespace App\Services\Shipping;

/**
 * A single shipping option returned by a shipping provider adapter. `confirmed`
 * is false for oversized/freight items whose real cost is set by admin later
 * ("Ongkir akan dikonfirmasi").
 */
final class ShippingQuote
{
    public function __construct(
        public readonly string $providerCode,
        public readonly string $serviceCode,
        public readonly string $label,
        public readonly string $type,          // regular|cargo|pickup|fleet|manual
        public readonly float $cost,
        public readonly float $packingFee,
        public readonly float $handlingFee,
        public readonly float $insuranceFee,
        public readonly int $billableWeightGrams,
        public readonly bool $confirmed = true,
        public readonly ?string $estimatedDays = null,
        public readonly ?string $note = null,
    ) {
    }

    public function totalShipping(): float
    {
        return round($this->cost + $this->packingFee + $this->handlingFee + $this->insuranceFee, 2);
    }

    public function toArray(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'service_code' => $this->serviceCode,
            'label' => $this->label,
            'type' => $this->type,
            'cost' => $this->cost,
            'packing_fee' => $this->packingFee,
            'handling_fee' => $this->handlingFee,
            'insurance_fee' => $this->insuranceFee,
            'billable_weight_grams' => $this->billableWeightGrams,
            'confirmed' => $this->confirmed,
            'estimated_days' => $this->estimatedDays,
            'note' => $this->note,
        ];
    }
}
