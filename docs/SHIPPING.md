# Shipping: volumetric weight & provider architecture

## Billable weight

Shipping is priced on the **billable weight**, the greater of actual and volumetric
weight (spec §16):

```
berat_volumetrik = panjang × lebar × tinggi ÷ divisor      (cm → kg)
berat_ditagihkan = max(berat_aktual, berat_volumetrik)      (rounded up)
```

The **divisor is never hardcoded** — it is configured per courier/service in
`shipping_services.volumetric_divisor`, with a global fallback in
`shipping_settings.default_volumetric_divisor`. The rounding step
(`weight_rounding_grams`, default 1000 g) is also configurable.

The maths lives in `App\Services\Shipping\WeightCalculator` (pure, unit-tested):

```php
$grams = $calc->billableGrams(
    actualGrams: $totalActualWeight,
    totalVolumeCm3: $sumOfLWH_timesQty,
    divisor: $service->volumetric_divisor,   // e.g. 6000 reguler, 4000 kargo
    roundingGrams: 1000,
);
```

For a multi-item cart, actual weight and total volume are summed across items
(respecting per-product `package_count`), then billable weight is derived once.

## Admin-configurable knobs

- `shipping_settings`: packing fee, handling fee, insurance %, free-shipping
  threshold, default divisor, rounding.
- `shipping_zones`: named zones matched against the destination **province**
  (empty province list = catch-all).
- `shipping_services` + `shipping_rates`: per-zone `price_per_kg`, `min_price`,
  `base_price`; per-service min/max weight and estimated days.

Cost formula (weight driver):
`max(min_price, base_price + billableKg × price_per_kg)`, zeroed when the subtotal
reaches the free-shipping threshold.

## Modular provider architecture

`App\Services\ShippingService::quotesFor(Cart, province)` returns an array of
`ShippingQuote` value objects. Each `shipping_services` row has a `type`
(`regular|cargo|pickup|fleet|manual`) and its provider a `driver`, which selects a
pricing strategy. This makes it trivial to add a real courier API or an ongkir
aggregator **without touching checkout**:

1. Insert a `shipping_providers` row with a new `driver` (e.g. `rajaongkir`).
2. Add a branch/adapter that turns the API response into `ShippingQuote` objects.
3. Checkout, cart, and order code are unchanged — they only consume `ShippingQuote`.

### Oversized / freight / pickup

Products flagged `requires_freight` also surface a **cargo** option and a
warehouse **pickup** option; products flagged `pickup_only` skip couriers entirely.
When a quote's `confirmed` is `false` (freight/manual), the order is created in
status **Menunggu Konfirmasi Ongkir**; an admin later confirms the real cost via
`OrderService::confirmShippingCost`, which recomputes the grand total and notifies
the customer. There is always a **manual "Ongkir Dikonfirmasi" fallback** so
checkout never dead-ends if no automatic rate matches.

## What the customer vs admin sees

- **Customer**: billable weight, courier, service, estimated time, and the ongkir
  (or "Dikonfirmasi").
- **Admin**: the full breakdown (actual vs volumetric, packing/handling/insurance)
  and the ability to override/confirm shipping before payment.
