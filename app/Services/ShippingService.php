<?php

namespace App\Services;

use App\Models\CargoRate;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\IndahCargoRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingService as ShippingServiceModel;
use App\Models\ShippingSetting;
use App\Services\Shipping\RajaOngkirClient;
use App\Services\Shipping\ShippingContext;
use App\Services\Shipping\ShippingDestination;
use App\Services\Shipping\ShippingQuote;
use App\Services\Shipping\WeightCalculator;
use Illuminate\Database\Eloquent\Collection;

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
        private readonly RajaOngkirClient $rajaOngkir,
    ) {}

    /**
     * @param  ShippingDestination|null  $destination  alamat lengkap (kecamatan/ID kelurahan)
     *                                                 untuk tarif kurir reguler via API
     * @return ShippingQuote[]
     */
    public function quotesFor(Cart $cart, string $destinationProvince, ?string $destinationCity = null, ?ShippingDestination $destination = null): array
    {
        $context = $this->contextFor($cart, $destinationProvince, $destinationCity, $destination?->district);
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

        // Kurir reguler (JNE, J&T, …) via API — hanya paket yang memang bisa
        // dibawa kurir: bukan barang kargo (panel/baterai) dan di bawah batas berat.
        if ($destination && ! $context->hasFreightItem && $this->rajaOngkir->enabled()) {
            array_push($quotes, ...$this->courierQuotes($context, $config, $destination));
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

    /**
     * Estimasi ongkir untuk SATU produk (halaman detail) tanpa keranjang:
     * keranjang sementara di memori dengan satu baris item.
     *
     * @return ShippingQuote[]
     */
    public function quotesForProduct(Product $product, ?ProductVariant $variant, int $quantity, string $destinationProvince, ?string $destinationCity = null, ?ShippingDestination $destination = null): array
    {
        $item = new CartItem;
        $item->quantity = max(1, $quantity);
        $item->setRelation('product', $product);
        $item->setRelation('variant', $variant);

        $cart = new Cart;
        $cart->setRelation('items', new Collection([$item]));

        return $this->quotesFor($cart, $destinationProvince, $destinationCity, $destination);
    }

    public function contextFor(Cart $cart, string $destinationProvince, ?string $destinationCity = null, ?string $destinationDistrict = null): ShippingContext
    {
        $actual = 0;
        $volume = 0.0;
        $packableActual = 0;
        $packableVolume = 0.0;
        $hasFreight = false;
        $hasPickup = false;
        $packages = 0;
        $maxUnit = 0;

        // Items at/above this per-unit weight need wooden-crate packing (0 = all items).
        $packingThreshold = (int) $this->config()->packing_min_item_grams;

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product || $product->requires_quotation) {
                continue;
            }
            $qty = (int) $item->quantity;
            $unitWeight = $item->variant?->weightGrams() ?? (int) $product->weight_grams;
            $unitVolume = $item->variant
                ? $item->variant->volumeCm3()
                : (float) $product->length_cm * (float) $product->width_cm * (float) $product->height_cm;

            $actual += $unitWeight * $qty;
            $volume += $unitVolume * $qty;
            $packages += (int) $product->package_count * $qty;
            // Kolli terberat: paket berisi beberapa kolli, jadi berat unitnya
            // dibagi jumlah kolli (aturan forklift kargo per kolli > 200 kg).
            $maxUnit = max($maxUnit, intdiv($unitWeight, max(1, (int) $product->package_count)));

            // Only heavy-enough units contribute to the packing charge.
            if ($packingThreshold <= 0 || $unitWeight >= $packingThreshold) {
                $packableActual += $unitWeight * $qty;
                $packableVolume += $unitVolume * $qty;
            }

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
            packableActualGrams: $packableActual,
            packableVolumeCm3: $packableVolume,
            destinationDistrict: $destinationDistrict,
            maxUnitGrams: $maxUnit,
        );
    }

    private function quoteForService(ShippingServiceModel $service, ShippingContext $ctx, ShippingSetting $config): ?ShippingQuote
    {
        // Indah Cargo prices per destination CITY (not by zone), so it has its own path.
        if ($service->provider->driver === 'indah') {
            return $this->indahQuote($service, $ctx, $config);
        }
        // Ekspedisi kargo dengan daftar harga statis per tujuan (mis. Buana Raya Cargo).
        if ($service->provider->driver === 'cargo_table') {
            return $this->cargoTableQuote($service, $ctx, $config);
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

        // Wooden-crate packing (Rp/kg) is charged only on the billable weight of items
        // heavy enough to need crating — light items are exempt.
        $packableGrams = $this->weights->billableGrams($ctx->packableActualGrams, $ctx->packableVolumeCm3, $divisor, 1000);
        $packingKg = $this->weights->toBillableKg($packableGrams);
        $packing = round((float) $config->packing_fee * $packingKg, 2);

        return new ShippingQuote(
            providerCode: $service->provider->code,
            serviceCode: $service->code,
            label: $service->provider->name.' — '.$service->name,
            type: $service->type,
            cost: round($billableKg * $perKg, 2),
            packingFee: $packing,
            handlingFee: (float) $config->handling_fee,
            insuranceFee: round($ctx->subtotal * (float) $config->insurance_percent / 100, 2),
            billableWeightGrams: $billableKg * 1000,
            confirmed: true,
            estimatedDays: $service->estimated_days,
        );
    }

    /**
     * Tarif kargo dari tabel cargo_rates (driver 'cargo_table', mis. Buana Raya Cargo):
     * per kg dengan minimum kg per tujuan, volumetrik per tujuan, biaya
     * forklift untuk kolli sangat berat, dan hanya ditawarkan untuk kiriman
     * di atas ambang berat (kebijakan toko: paket proyek ≥ 50 kg).
     */
    private function cargoTableQuote(ShippingServiceModel $service, ShippingContext $ctx, ShippingSetting $config): ?ShippingQuote
    {
        $provider = $service->provider;
        $options = is_array($provider->config) ? $provider->config : (json_decode((string) $provider->config, true) ?: []);

        $rate = CargoRate::lookup($provider->code, $ctx->destinationCity, $ctx->destinationDistrict);
        if (! $rate) {
            return null;
        }

        $divisor = (int) ($rate->volumetric_divisor ?: $service->volumetric_divisor ?: 4000);
        $billable = $this->weights->billableGrams($ctx->totalActualGrams, $ctx->totalVolumeCm3, $divisor, 1000);

        $minShipment = (int) ($options['min_shipment_grams'] ?? $service->min_weight_grams ?? 0);
        if ($billable < $minShipment) {
            return null; // kiriman ringan: pakai kurir reguler / Indah
        }

        $billableKg = max((int) $rate->min_kg, $this->weights->toBillableKg($billable));
        $cost = round($billableKg * (float) $rate->price_per_kg, 2);

        // Kolli di atas ambang (200 kg) kena biaya forklift sekali.
        $forkliftOver = (int) ($options['forklift_over_grams'] ?? 0);
        if ($forkliftOver > 0 && $ctx->maxUnitGrams > $forkliftOver) {
            $cost += (float) ($options['forklift_fee'] ?? 0);
        }

        // Packing kayu per kg hanya untuk item berat — aturan yang sama dengan Indah.
        $packableGrams = $this->weights->billableGrams($ctx->packableActualGrams, $ctx->packableVolumeCm3, $divisor, 1000);
        $packing = round((float) $config->packing_fee * $this->weights->toBillableKg($packableGrams), 2);

        return new ShippingQuote(
            providerCode: $provider->code,
            serviceCode: $service->code,
            label: $provider->name.' — '.$service->name,
            type: $service->type,
            cost: $cost,
            packingFee: $packing,
            handlingFee: (float) $config->handling_fee,
            insuranceFee: round($ctx->subtotal * (float) $config->insurance_percent / 100, 2),
            billableWeightGrams: $billableKg * 1000,
            confirmed: true,
            estimatedDays: $service->estimated_days,
            note: $options['note'] ?? null,
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

    /**
     * Tarif kurir reguler dari RajaOngkir/Komerce untuk tujuan ini. Packing kayu
     * mengikuti aturan Indah (hanya item berat), gratis ongkir mengikuti ambang.
     *
     * @return ShippingQuote[]
     */
    private function courierQuotes(ShippingContext $ctx, ShippingSetting $config, ShippingDestination $destination): array
    {
        $divisor = (int) ($config->default_volumetric_divisor ?: 6000);
        $billable = max(1000, $this->weights->billableGrams($ctx->totalActualGrams, $ctx->totalVolumeCm3, $divisor, 1000));
        if ($billable > $this->rajaOngkir->maxWeightGrams()) {
            return [];
        }

        $destinationId = $this->resolveCourierDestination($destination);
        if (! $destinationId) {
            return [];
        }

        $packableGrams = $this->weights->billableGrams($ctx->packableActualGrams, $ctx->packableVolumeCm3, $divisor, 1000);
        $packing = round((float) $config->packing_fee * $this->weights->toBillableKg($packableGrams), 2);
        $freeShipping = $config->free_shipping_min_subtotal !== null && $ctx->subtotal >= (float) $config->free_shipping_min_subtotal;

        $quotes = [];
        $rows = $this->rajaOngkir->curate($this->rajaOngkir->domesticCost($destinationId, $billable), $billable);
        foreach ($rows as $row) {
            $quotes[] = new ShippingQuote(
                providerCode: strtoupper($row['courier']),
                serviceCode: $row['service'],
                label: $row['courier_name'].' — '.$row['service'].($row['description'] !== '' ? ' ('.$row['description'].')' : ''),
                type: 'regular',
                cost: $freeShipping ? 0.0 : round($row['cost'], 2),
                packingFee: $packing,
                handlingFee: (float) $config->handling_fee,
                insuranceFee: round($ctx->subtotal * (float) $config->insurance_percent / 100, 2),
                billableWeightGrams: $billable,
                confirmed: true,
                estimatedDays: $this->etdLabel($row['etd']),
            );
        }

        return $quotes;
    }

    /**
     * ID kelurahan tujuan: dari alamat bila sudah tersimpan; alamat lama dicari
     * lewat "kecamatan kota" lalu hasilnya disimpan ke alamat supaya sekali saja.
     */
    private function resolveCourierDestination(ShippingDestination $destination): ?int
    {
        if ($destination->courierDestinationId) {
            return $destination->courierDestinationId;
        }

        $keyword = $destination->searchKeyword();
        if (! $keyword) {
            return null;
        }

        $results = $this->rajaOngkir->searchDestination($keyword, 10);
        if (! $results) {
            return null;
        }

        $normalize = fn (?string $s) => trim((string) preg_replace('/^(KOTA|KAB\.?|KABUPATEN)\s+/i', '', strtoupper(trim((string) $s))));
        $city = $normalize($destination->city);
        $match = collect($results)->first(fn ($r) => $city !== '' && $normalize($r['city']) === $city) ?? $results[0];

        if ($destination->addressId) {
            CustomerAddress::whereKey($destination->addressId)->update([
                'courier_destination_id' => $match['id'],
                'courier_destination_label' => $match['label'],
            ]);
        }

        return (int) $match['id'];
    }

    /** "1-2 day" / "2 days" / "3" → "1-2 hari"; "0 day" → "hari ini". */
    private function etdLabel(string $etd): ?string
    {
        $etd = trim((string) preg_replace('/\b(days?|hari)\b/i', '', $etd));
        if ($etd === '') {
            return null;
        }

        return preg_match('/^0(-0)?$/', $etd) ? 'hari ini' : $etd.' hari';
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
    public function findQuote(Cart $cart, string $province, string $providerCode, string $serviceCode, ?string $city = null, ?ShippingDestination $destination = null): ?ShippingQuote
    {
        foreach ($this->quotesFor($cart, $province, $city, $destination) as $quote) {
            if ($quote->providerCode === $providerCode && $quote->serviceCode === $serviceCode) {
                return $quote;
            }
        }

        return null;
    }
}
