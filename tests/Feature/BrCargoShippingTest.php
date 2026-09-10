<?php

namespace Tests\Feature;

use App\Models\CargoRate;
use App\Models\Cart;
use App\Models\ShippingProvider;
use App\Services\CartService;
use App\Services\Shipping\ShippingDestination;
use App\Services\Shipping\ShippingQuote;
use App\Services\ShippingService;
use Database\Seeders\BrCargoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BR Cargo (daftar harga BR BDG 2026): tarif per kg dengan minimum kg per
 * tujuan, volumetrik ÷ 4000 (÷ 6000 untuk Banjarmasin/Balikpapan/Samarinda),
 * forklift Rp 150.000 untuk kolli > 200 kg, hanya untuk kiriman ≥ 50 kg,
 * tujuan cocok lewat nama kota atau kecamatan.
 */
class BrCargoShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BrCargoSeeder::class);
        $this->actingAs($this->customer());
    }

    private function cartWith(int $unitGrams, int $qty = 1, array $dims = [50, 40, 30]): Cart
    {
        $product = $this->stockedProduct(10, [
            'weight_grams' => $unitGrams, 'length_cm' => $dims[0], 'width_cm' => $dims[1], 'height_cm' => $dims[2], 'price' => 1_000_000,
        ]);
        $cart = app(CartService::class)->current();
        $cart->items()->delete(); // keranjang baru per skenario
        app(CartService::class)->addItem($product, null, $qty);

        return $cart->fresh(['items.product', 'items.variant']);
    }

    private function br(array $quotes): ?ShippingQuote
    {
        return collect($quotes)->first(fn ($q) => $q->providerCode === 'BR');
    }

    public function test_seeder_imports_the_tariff_with_per_destination_rules(): void
    {
        $this->assertSame(166, CargoRate::where('provider_code', 'BR')->count());
        $this->assertTrue(ShippingProvider::where('code', 'BR')->where('driver', 'cargo_table')->exists());

        $makassar = CargoRate::lookup('BR', 'Makassar');
        $this->assertSame([10, 6000.0, 4000], [(int) $makassar->min_kg, (float) $makassar->price_per_kg, (int) $makassar->volumetric_divisor]);
        $this->assertSame(6000, (int) CargoRate::lookup('BR', 'Kota Banjarmasin')->volumetric_divisor);
        $this->assertSame(250, (int) CargoRate::lookup('BR', 'Takalar')->min_kg);

        // Idempotent.
        $this->seed(BrCargoSeeder::class);
        $this->assertSame(166, CargoRate::where('provider_code', 'BR')->count());
    }

    public function test_heavy_shipment_is_priced_per_kg_with_the_destination_minimum(): void
    {
        // 60 kg (3 × 20 kg, volume kecil) ke Makassar: min 10 kg → 60 × 6.000 = 360.000.
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(20000, 3), 'Sulawesi Selatan', 'Kota Makassar'));
        $this->assertNotNull($quote);
        $this->assertEquals(360_000, $quote->cost);
        $this->assertSame(60_000, $quote->billableWeightGrams);
        $this->assertTrue($quote->confirmed);
        $this->assertSame('BR Cargo — Darat / Kapal Cepat', $quote->label);
        $this->assertStringContainsString('jadwal', strtolower((string) $quote->estimatedDays));

        // Takalar minimum 250 kg → 60 kg tetap ditagih 250 × 10.000.
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(20000, 3), 'Sulawesi Selatan', 'Takalar'));
        $this->assertEquals(2_500_000, $quote->cost);
        $this->assertSame(250_000, $quote->billableWeightGrams);
    }

    public function test_light_shipments_and_unknown_destinations_are_not_offered(): void
    {
        // 30 kg → di bawah ambang 50 kg (kebijakan toko).
        $this->assertNull($this->br(app(ShippingService::class)->quotesFor($this->cartWith(30000), 'Sulawesi Selatan', 'Makassar')));

        // Tujuan tidak ada di daftar (Bandung sendiri / Jawa Barat).
        $this->assertNull($this->br(app(ShippingService::class)->quotesFor($this->cartWith(20000, 3), 'Jawa Barat', 'Kota Bandung')));
    }

    public function test_volumetric_weight_uses_the_destination_divisor(): void
    {
        // 60 kg aktual tapi 100×100×80 cm = 800.000 cm³ → ÷4000 = 200 kg ke Makassar.
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(60000, 1, [100, 100, 80]), 'Sulawesi Selatan', 'Makassar'));
        $this->assertEquals(200 * 6000, $quote->cost);

        // Banjarmasin ÷6000 → 133,3 → 134 kg × 5.000.
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(60000, 1, [100, 100, 80]), 'Kalimantan Selatan', 'Banjarmasin'));
        $this->assertEquals(134 * 5000, $quote->cost);
    }

    public function test_forklift_fee_applies_to_a_single_package_over_200kg(): void
    {
        // Satu baterai 256 kg ke Makassar: 256 × 6.000 + forklift 150.000.
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(256000), 'Sulawesi Selatan', 'Makassar'));
        $this->assertEquals(256 * 6000 + 150_000, $quote->cost);

        // Total 240 kg tapi per unit 60 kg → tanpa forklift.
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(60000, 4), 'Sulawesi Selatan', 'Makassar'));
        $this->assertEquals(240 * 6000, $quote->cost);
    }

    public function test_destination_matches_by_district_when_the_city_is_not_listed(): void
    {
        // "Bati Bati" adalah kecamatan di Kab. Tanah Laut; alamat pelanggan: kota "Kab. Tanah Laut", kecamatan "Bati-Bati".
        $dest = new ShippingDestination(province: 'Kalimantan Selatan', city: 'Kab. Tanah Laut', district: 'Bati Bati');
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(20000, 3), 'Kalimantan Selatan', 'Kab. Tanah Laut', $dest));
        $this->assertNotNull($quote);
        // Kota "Tanah Laut" sendiri ada di daftar (min 50, 11.000) dan menang karena kota dicek dulu.
        $this->assertEquals(60 * 11000, $quote->cost);

        // Kota tidak terdaftar, kecamatan terdaftar → tarif kecamatan.
        $dest = new ShippingDestination(province: 'Kalimantan Selatan', city: 'Kab. Antah Berantah', district: 'Asam Asam');
        $quote = $this->br(app(ShippingService::class)->quotesFor($this->cartWith(20000, 3), 'Kalimantan Selatan', 'Kab. Antah Berantah', $dest));
        $this->assertEquals(60 * 12000, $quote->cost);
    }
}
