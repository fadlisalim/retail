<?php

namespace Tests\Feature;

use App\Models\IndahCargoRate;
use App\Models\ShippingProvider;
use App\Services\CartService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndahShippingTest extends TestCase
{
    use RefreshDatabase;

    private function seedIndah(): void
    {
        $provider = ShippingProvider::create([
            'code' => 'INDAH', 'name' => 'Indah Cargo', 'driver' => 'indah', 'is_active' => true,
        ]);
        $provider->services()->create(['code' => 'UDARA', 'name' => 'Udara', 'type' => 'regular', 'volumetric_divisor' => 6000, 'min_weight_grams' => 1000, 'is_active' => true]);
        $provider->services()->create(['code' => 'DARAT', 'name' => 'Darat', 'type' => 'cargo', 'volumetric_divisor' => 4000, 'min_weight_grams' => 10000, 'is_active' => true]);

        IndahCargoRate::create(['origin' => 'BANDUNG', 'destination_city' => 'SURABAYA', 'air_per_kg' => 16500, 'land_per_kg' => 4500]);
    }

    private function cartWith(int $weightGrams): \App\Models\Cart
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(10, [
            'weight_grams' => $weightGrams, 'length_cm' => 20, 'width_cm' => 20, 'height_cm' => 20,
        ]);
        $cart = app(CartService::class)->current();
        app(CartService::class)->addItem($product, null, 1);

        return $cart->fresh(['items.product', 'items.variant']);
    }

    public function test_indah_air_and_land_priced_by_city(): void
    {
        $this->seedIndah();
        $cart = $this->cartWith(5000); // 5 kg, small volume

        $quotes = collect(app(ShippingService::class)->quotesFor($cart, 'Jawa Timur', 'Kota Surabaya'));

        $air = $quotes->firstWhere('serviceCode', 'UDARA');
        $land = $quotes->firstWhere('serviceCode', 'DARAT');

        $this->assertNotNull($air);
        $this->assertNotNull($land);
        $this->assertEquals(82500, $air->cost);  // 5 kg x 16.500
        $this->assertEquals(45000, $land->cost);  // min 10 kg x 4.500
    }

    public function test_unknown_city_falls_back_to_manual(): void
    {
        $this->seedIndah();
        $cart = $this->cartWith(5000);

        $quotes = collect(app(ShippingService::class)->quotesFor($cart, 'Papua', 'Kota Antah Berantah'));

        $this->assertNull($quotes->firstWhere('serviceCode', 'UDARA'));
        $this->assertTrue($quotes->contains(fn ($q) => ! $q->confirmed)); // manual "dikonfirmasi"
    }
}
