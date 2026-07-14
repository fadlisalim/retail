<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\IndahCargoRate;
use App\Models\ShippingService as ShippingServiceModel;
use App\Models\ShippingSetting;
use App\Services\Shipping\ShippingContext;
use App\Services\Shipping\ShippingQuote;
use App\Services\Shipping\WeightCalculator;

/**
 * Produces shipping quotes for a cart + destination. The architecture is modular:
 * each shipping_services row names a `type` and its provider a `driver`, and this
 * service maps them to a pricing strategy. New couriers or an ongkir aggregator
 * API can be added by inserting rows / adding a driver branch WITHOUT touching
 * checkout. If nothing can price the shipment, a manual "ongkir dikonfirmasi"
 * fallback is always returned so checkout never dead-ends.
 */
class ShippingService
{
    public function __construct(
        private readonly WeightCalculator $weights,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * @return ShippingQuote[]
     */
    public function quotesFor(Cart $cart, string $destinationProvince, ?string $destinationCity = null): array
    {
        $context = $this->contextFor($cart, $destinationProvince, $destinationCity);
        $config = $this->config();

        // Pickup-only carts (e.g. project surplus that must be collected) skip courier.
        if ($context->hasPickupOnlyItem) {
            return [$this->pickupQuote($context)];
        }

        $quotes = [];
        $services = ShippingServiceModel::where('is_active', true)
            ->whereHas('provider', fn ($q) => $q->where('is_active', true))
            ->with(['provider', 'rates.zone'])
            ->get();

        foreach ($services as $service) {
            $quote = $this->quoteForService($service, $context, $config);
            if ($quote) {
                $quotes[] = $quote;
            }
        }

        // Oversized/freight items always offer a to-be-confirmed cargo + pickup path.
        if ($context->hasFreightItem) {
            $quotes[] = $this->freightQuote($context);
            $quotes[] = $this->pickupQuote($context);
        }

        if (empty($quotes)) {
            $quotes[] = $this->manualQuote($context);
        }

        return $quotes;
    }

    public function contextFor(Cart $cart, string $destinationProvince, ?string $destinationCity = null): ShippingContext
    {
        $actual = 0;
        $volume = 0.0;
        $hasFreight = false;
        $hasPickup = false;
        $packages = 0;

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product || $product->requires_quotation) {
                continue;
            }
            $qty = (int) $item->quantity;
            $actual += ($item->variant?->weightGrams() ?? (int) $product->weight_grams) * $qty;
            $volume += ((float) $product->length_cm * (float) $product->width_cm * (float) $product->height_cm) * $qty;
            $packages += (int) $product->package_count * $qty;
            $hasFreight = $hasFreight || $product->requires_freight;
            $hasPickup = $hasPickup || $product->pickup_only;
        }

        return new ShippingContext(
            totalActualGrams: $actual,
            totalVolumeCm3: $volume,
            subtotal: (float) $cart->items->sum(fn ($i) => $i->currentUnitPrice() * $i->quantity),
            destinationProvince: $destinationProvince,
            hasFreightItem: $hasFreight,
            hasPickupOnlyItem: $hasPickup,
            packageCount: max(1, $packages),
            destinationCity: $destinationCity,
        );
    }

    private function quoteForService(ShippingServiceModel $service, ShippingContext $ctx, ShippingSetting $config): ?ShippingQuote
    {
        // Indah Cargo prices per destination CITY (not by zone), so it has its own path.
        if ($service->provider->driver === 'indah') {
            return $this->indahQuote($service, $ctx, $config);
        }

        $divisor = $service->volumetric_divisor ?: $config->default_volumetric_divisor;
        $billable = $this->weights->billableGrams(
            $ctx->totalActualGrams,
            $ctx->totalVolumeCm3,
            $divisor,
            $config->weight_rounding_grams,
        );

        if ($service->max_weight_grams && $billable > $service->max_weight_grams) {
            return null; // package too heavy for this service (e.g. reguler)
        }
        $billable = max($billable, (int) $service->min_weight_grams);

        // Non-courier service types have their own strategies.
        if (in_array($service->type, ['pickup'], true)) {
            return $this->pickupQuote($ctx);
        }
        if (in_array($service->type, ['manual', 'fleet'], true)) {
            return $this->manualQuote($ctx, $service->name, $service->provider->code, $service->code);
        }

        $rate = $this->matchRate($service, $ctx->destinationProvince);
        if (! $rate) {
            return null;
        }

        $billableKg = $this->weights->toBillableKg($billable);
        $cost = max((float) $rate->min_price, (float) $rate->base_price + $billableKg * (float) $rate->price_per_kg);

        // Free-shipping threshold zeroes the courier cost (fees still apply).
        if ($config->free_shipping_min_subtotal !== null && $ctx->subtotal >= (float) $config->free_shipping_min_subtotal) {
            $cost = 0.0;
        }

        return new ShippingQuote(
            providerCode: $service->provider->code,
            serviceCode: $service->code,
            label: $service->provider->name.' — '.$service->name,
            type: $service->type,
            cost: round($cost, 2),
            packingFee: (float) $config->packing_fee,
            handlingFee: (float) $config->handling_fee,
            insuranceFee: round($ctx->subtotal * (float) $config->insurance_percent / 100, 2),
            billableWeightGrams: $billable,
            confirmed: true,
            estimatedDays: $service->estimated_days,
        );
    }

    /**
     * Indah Cargo tariff: per-kg by destination city, chosen by service (UDARA = air,
     * DARAT = land/sea). Land/sea bills a 10 kg minimum. Falls back to no quote (then
     * the manual "ongkir dikonfirmasi" option) when the city isn't in the tariff.
     */
    private function indahQuote(ShippingServiceModel $service, ShippingContext $ctx, ShippingSetting $config): ?ShippingQuote
    {
        if (! $ctx->destinationCity) {
            return null;
        }

        $rate = IndahCargoRate::lookup($ctx->destinationCity);
        if (! $rate) {
            return null;
        }

        $isAir = $service->code === 'UDARA';
        $perKg = (float) ($isAir ? $rate->air_per_kg : $rate->land_per_kg);
        if ($perKg <= 0) {
            return null;
        }

        $divisor = $service->volumetric_divisor ?: ($isAir ? 6000 : 4000);
        $billable = $this->weights->billableGrams($ctx->totalActualGrams, $ctx->totalVolumeCm3, $divisor, 1000);
        $billableKg = max($isAir ? 1 : 10, $this->weights->toBillableKg($billable));

        return new ShippingQuote(
            providerCode: $service->provider->code,
            serviceCode: $service->code,
            label: $service->provider->name.' — '.$service->name,
            type: $service->type,
            cost: round($billableKg * $perKg, 2),
            packingFee: (float) $config->packing_fee,
            handlingFee: (float) $config->handling_fee,
            insuranceFee: round($ctx->subtotal * (float) $config->insurance_percent / 100, 2),
            billableWeightGrams: $billableKg * 1000,
            confirmed: true,
            estimatedDays: $service->estimated_days,
        );
    }

    private function matchRate(ShippingServiceModel $service, string $province)
    {
        foreach ($service->rates as $rate) {
            $provinces = $rate->zone?->provinces ?? [];
            if (empty($provinces) || in_array($province, $provinces, true)) {
                return $rate;
            }
        }

        return $service->rates->first();
    }

    private function pickupQuote(ShippingContext $ctx): ShippingQuote
    {
        return new ShippingQuote(
            providerCode: 'pickup', serviceCode: 'warehouse', label: 'Ambil di Gudang Rekasurya',
            type: 'pickup', cost: 0, packingFee: 0, handlingFee: 0, insuranceFee: 0,
            billableWeightGrams: $ctx->totalActualGrams, confirmed: true,
            estimatedDays: 'Sesuai jadwal', note: 'Barang diambil sendiri di gudang.',
        );
    }

    private function freightQuote(ShippingContext $ctx): ShippingQuote
    {
        return new ShippingQuote(
            providerCode: 'cargo', serviceCode: 'freight', label: 'Kargo (ongkir dikonfirmasi)',
            type: 'cargo', cost: 0, packingFee: 0, handlingFee: 0, insuranceFee: 0,
            billableWeightGrams: $ctx->totalActualGrams, confirmed: false,
            note: 'Ongkir kargo akan dikonfirmasi admin sebelum pembayaran.',
        );
    }

    private function manualQuote(ShippingContext $ctx, string $label = 'Ongkir Dikonfirmasi', string $provider = 'manual', string $service = 'manual'): ShippingQuote
    {
        return new ShippingQuote(
            providerCode: $provider, serviceCode: $service, label: $label,
            type: 'manual', cost: 0, packingFee: 0, handlingFee: 0, insuranceFee: 0,
            billableWeightGrams: $ctx->totalActualGrams, confirmed: false,
            note: 'Ongkir akan dihitung dan dikonfirmasi oleh admin.',
        );
    }

    private function config(): ShippingSetting
    {
        return ShippingSetting::first() ?? new ShippingSetting([
            'packing_fee' => 0, 'handling_fee' => 0, 'insurance_percent' => 0,
            'default_volumetric_divisor' => (int) config('rekasurya.shipping.default_volumetric_divisor', 6000),
            'weight_rounding_grams' => 1000,
        ]);
    }

    /** Rebuild a quote object from a persisted selection (used at checkout confirm). */
    public function findQuote(Cart $cart, string $province, string $providerCode, string $serviceCode, ?string $city = null): ?ShippingQuote
    {
        foreach ($this->quotesFor($cart, $province, $city) as $quote) {
            if ($quote->providerCode === $providerCode && $quote->serviceCode === $serviceCode) {
                return $quote;
            }
        }

        return null;
    }
}
