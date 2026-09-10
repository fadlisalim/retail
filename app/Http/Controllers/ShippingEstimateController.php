<?php

namespace App\Http\Controllers;

use App\Models\IndahCargoRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Services\Shipping\GeoLocator;
use App\Services\Shipping\RajaOngkirClient;
use App\Services\Shipping\ShippingDestination;
use App\Services\Shipping\ShippingQuote;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * "Berapa ongkir ke lokasi saya?" di halaman produk — publik (tamu boleh).
 * Tujuan bisa dari GPS (lat/lng → reverse geocode → kelurahan), dari
 * dropdown wilayah (regions), dari tujuan yang pernah diingat browser, atau
 * provinsi/kota Indah bila integrasi kurir nonaktif.
 */
class ShippingEstimateController extends Controller
{
    public function __construct(
        private readonly ShippingService $shipping,
        private readonly RajaOngkirClient $courier,
        private readonly GeoLocator $geo,
    ) {}

    /** Provinsi → kota dari tabel tarif Indah (fallback bila wilayah RajaOngkir tidak aktif). */
    public function indahCities(): JsonResponse
    {
        return response()->json(Cache::remember('indah:cities-by-province', now()->addHours(12), fn () => IndahCargoRate::citiesByProvince()));
    }

    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'subdistrict_id' => ['nullable', 'integer', 'exists:regions,id'],
            'courier_destination_id' => ['nullable', 'integer', 'min:1'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = ! empty($data['variant_id']) ? ProductVariant::where('product_id', $product->id)->find($data['variant_id']) : null;
        $qty = max((int) ($data['qty'] ?? 1), max(1, (int) $product->min_purchase));

        $place = $this->resolveDestination($data);
        if (! $place) {
            return response()->json(['message' => 'Lokasi belum dikenali. Coba pilih provinsi, kota, dan kecamatan secara manual.'], 422);
        }

        $destination = new ShippingDestination(
            province: (string) ($place['province'] ?? ''),
            city: $place['city'] ?? null,
            district: $place['district'] ?? null,
            subdistrict: $place['subdistrict'] ?? null,
            postalCode: $place['postal_code'] ?? null,
            courierDestinationId: ! empty($place['courier_destination_id']) ? (int) $place['courier_destination_id'] : null,
        );

        $quotes = collect($this->shipping->quotesForProduct($product, $variant, $qty, $destination->province, $destination->city, $destination))
            ->sortBy(fn (ShippingQuote $q) => [$q->type === 'pickup' ? 2 : ($q->confirmed ? 0 : 1), $q->totalShipping()])
            ->values();

        $unitGrams = $variant?->weightGrams() ?? (int) $product->weight_grams;

        return response()->json([
            'destination' => [
                'label' => $place['label'] ?? implode(', ', array_filter([$destination->subdistrict, $destination->district, $destination->city, $destination->province])),
                'province' => $destination->province, 'city' => $destination->city, 'district' => $destination->district,
                'subdistrict' => $destination->subdistrict, 'postal_code' => $destination->postalCode,
                'courier_destination_id' => $destination->courierDestinationId,
            ],
            'qty' => $qty,
            'weight_grams' => $unitGrams * $qty,
            'quotes' => $quotes->map(fn (ShippingQuote $q) => [
                'provider_code' => $q->providerCode, 'service_code' => $q->serviceCode, 'label' => $q->label, 'type' => $q->type,
                'cost' => $q->cost, 'packing_fee' => $q->packingFee, 'other_fees' => round($q->handlingFee + $q->insuranceFee, 2),
                'total' => $q->totalShipping(), 'rupiah' => rupiah($q->totalShipping()),
                'billable_kg' => (int) ceil($q->billableWeightGrams / 1000),
                'estimated_days' => $q->estimatedDays, 'confirmed' => $q->confirmed, 'note' => $q->note,
            ])->all(),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function resolveDestination(array $data): ?array
    {
        // 1. GPS → nama wilayah → (bila kurir aktif) ID kelurahan RajaOngkir.
        if (isset($data['lat'], $data['lng'])) {
            $place = $this->geo->reverse((float) $data['lat'], (float) $data['lng']);
            if (! $place || ! $place['city']) {
                return null;
            }

            return $place + ['courier_destination_id' => $this->courierIdFor($place)];
        }

        // 2. Dropdown wilayah (tabel regions).
        if (! empty($data['subdistrict_id'])) {
            $sub = Region::find($data['subdistrict_id']);
            if (! $sub || $sub->type !== 'subdistrict') {
                return null;
            }
            $chain = collect($sub->chain())->keyBy('type');

            return [
                'province' => $chain['province']->name ?? null, 'city' => $chain['city']->name ?? null,
                'district' => $chain['district']->name ?? null, 'subdistrict' => $sub->name,
                'postal_code' => $sub->postal_code, 'courier_destination_id' => (int) $sub->code, 'label' => $sub->courierLabel(),
            ];
        }

        // 3. Tujuan yang diingat browser / pilihan provinsi-kota (Indah).
        if (! empty($data['city'])) {
            $place = [
                'province' => $data['province'] ?? null, 'city' => $data['city'], 'district' => $data['district'] ?? null,
                'subdistrict' => $data['subdistrict'] ?? null, 'postal_code' => null,
            ];
            $place['label'] = implode(', ', array_filter([$place['subdistrict'], $place['district'], $place['city'], $place['province']]));
            $place['courier_destination_id'] = ! empty($data['courier_destination_id'])
                ? (int) $data['courier_destination_id']
                : ($place['district'] ? $this->courierIdFor($place) : null);

            return $place;
        }

        return null;
    }

    /** Cari ID kelurahan RajaOngkir dari nama (cache 7 hari di klien API); null bila kurir nonaktif/tidak ketemu. */
    private function courierIdFor(array $place): ?int
    {
        if (! $this->courier->enabled()) {
            return null;
        }

        $normalize = fn (?string $s) => trim((string) preg_replace('/^(KOTA|KAB\.?|KABUPATEN)\s+/i', '', strtoupper(trim((string) $s))));
        $city = $normalize($place['city'] ?? null);

        foreach ([[$place['subdistrict'] ?? null, $place['district'] ?? null], [$place['district'] ?? null, $place['city'] ?? null]] as $parts) {
            $keyword = trim(implode(' ', array_filter($parts)));
            if (mb_strlen($keyword) < 3) {
                continue;
            }
            $results = $this->courier->searchDestination($keyword, 10);
            $match = collect($results)->first(fn ($r) => $city !== '' && $normalize($r['city']) === $city) ?? ($results[0] ?? null);
            if ($match) {
                return (int) $match['id'];
            }
        }

        return null;
    }
}
