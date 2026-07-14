<?php

namespace Tests\Feature;

use App\Models\IndahCargoRate;
use App\Models\ShippingProvider;
use App\Models\ShippingSetting;
use App\Services\CartService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackingThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function seedIndah(): void
    {
        ShippingSetting::create([
            'id' => 1, 'packing_fee' => 5000, 'packing_min_item_grams' => 5000,
            'handling_fee' => 0, 'insurance_percent' => 0,
            'default_volumetric_divisor' => 6000, 'weight_rounding_grams' => 1000,
        ]);
        $p = ShippingProvider::create(['code' => 'INDAH', 'name' => 'Indah Cargo', 'driver' => 'indah', 'is_active' => true]);
        $p->services()->create(['code' => 'UDARA', 'name' => 'Udara', 'type' => 'regular', 'volumetric_divisor' => 6000, 'min_weight_grams' => 1000, 'is_active' => true]);
        IndahCargoRate::create(['origin' => 'BANDUNG', 'destination_city' => 'SURABAYA', 'province' => 'Jawa Timur', 'air_per_kg' => 16500, 'land_per_kg' => 4500]);
    }

    private function udaraQuote($cart)
    {
        return collect(app(ShippingService::class)->quotesFor($cart->fresh(['items.product', 'items.variant']), 'Jawa Timur', 'Surabaya'))
            ->firstWhere('serviceCode', 'UDARA');
    }

    /** Only items at/above the per-unit weight threshold incur wooden-crate packing. */
    public function test_packing_charged_only_for_heavy_items(): void
    {
        $this->seedIndah();
        $this->actingAs($this->customer());
        $svc = app(CartService::class);
        $cart = $svc->current();

        $light = $this->stockedProduct(10, ['weight_grams' => 2000, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10]);
        $svc->addItem($light, null, 1);
        $this->assertEquals(0, $this->udaraQuote($cart)->packingFee, 'light-only cart must have no packing');

        $heavy = $this->stockedProduct(10, ['weight_grams' => 8000, 'length_cm' => 20, 'width_cm' => 20, 'height_cm' => 20]);
        $svc->addItem($heavy, null, 1);
        // Packing on the 8 kg heavy item only: 8 × 5.000 = 40.000.
        $this->assertEquals(40000, $this->udaraQuote($cart)->packingFee);
    }
}
