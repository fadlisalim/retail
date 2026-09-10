<?php

namespace Tests\Feature;

use App\Models\IndahCargoRate;
use App\Models\Region;
use App\Models\ShippingProvider;
use Database\Seeders\BrCargoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Berapa ongkir ke lokasi saya?" di halaman produk: tamu boleh; tujuan dari
 * GPS (reverse geocode → kelurahan RajaOngkir), dari dropdown wilayah, atau
 * dari tujuan yang diingat browser; hasil = tarif semua ekspedisi untuk
 * produk × qty itu.
 */
class ShippingEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.rajaongkir.enabled' => true, 'services.rajaongkir.api_key' => 'k', 'services.rajaongkir.origin_id' => 4853,
            'services.rajaongkir.couriers' => 'jne:jnt', 'services.rajaongkir.base_url' => 'https://rajaongkir.komerce.id/api/v1',
        ]);

        $ok = fn (array $data) => Http::response(['meta' => ['code' => 200], 'data' => $data]);
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response(['address' => [
                'village' => 'Jongaya', 'city_district' => 'Kecamatan Tamalate', 'city' => 'Kota Makassar', 'state' => 'Sulawesi Selatan', 'postcode' => '90223',
            ]]),
            'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => $ok([
                ['id' => 55555, 'label' => 'JONGAYA, TAMALATE, MAKASSAR, SULAWESI SELATAN, 90223', 'province_name' => 'SULAWESI SELATAN', 'city_name' => 'MAKASSAR', 'district_name' => 'TAMALATE', 'subdistrict_name' => 'JONGAYA', 'zip_code' => '90223'],
            ]),
            'rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => $ok([
                ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 42000, 'etd' => '2-3 day'],
                ['name' => 'J&T', 'code' => 'jnt', 'service' => 'EZ', 'description' => 'Reguler', 'cost' => 36000, 'etd' => '3 day'],
            ]),
            '*' => Http::response(['meta' => ['code' => 404]], 404),
        ]);

        // Indah Cargo Makassar + Buana Raya.
        $indah = ShippingProvider::create(['code' => 'INDAH', 'name' => 'Indah Cargo', 'driver' => 'indah', 'is_active' => true]);
        $indah->services()->create(['code' => 'UDARA', 'name' => 'Via Udara', 'type' => 'regular', 'volumetric_divisor' => 6000, 'min_weight_grams' => 1000, 'is_active' => true]);
        IndahCargoRate::create(['origin' => 'BANDUNG', 'destination_city' => 'MAKASSAR', 'province' => 'Sulawesi Selatan', 'air_per_kg' => 16500, 'land_per_kg' => 4500]);
        $this->seed(BrCargoSeeder::class);
    }

    public function test_gps_estimate_resolves_the_village_and_lists_every_courier(): void
    {
        $product = $this->stockedProduct(10, ['name' => 'MC4 Sepasang', 'price' => 20800, 'weight_grams' => 50, 'length_cm' => 6, 'width_cm' => 4, 'height_cm' => 3]);

        $res = $this->postJson(route('shipping.estimate'), ['product_id' => $product->id, 'qty' => 2, 'lat' => -5.16, 'lng' => 119.41])
            ->assertOk()->json();

        $this->assertSame('Jongaya, Tamalate, Makassar, Sulawesi Selatan', $res['destination']['label']);
        $this->assertSame(55555, $res['destination']['courier_destination_id']);
        $this->assertSame(100, $res['weight_grams']);
        $codes = array_map(fn ($q) => $q['provider_code'].'|'.$q['service_code'], $res['quotes']);
        $this->assertContains('JNT|EZ', $codes);
        $this->assertContains('JNE|REG', $codes);
        $this->assertContains('INDAH|UDARA', $codes);
        // Termurah (yang pasti) di urutan pertama: Indah udara 1 kg × 16.500; Buana Raya tidak muncul (< 50 kg).
        $this->assertSame('INDAH|UDARA', $codes[0]);
        $this->assertSame('Rp 16.500', $res['quotes'][0]['rupiah']);
        $this->assertSame('JNT|EZ', $codes[1]);
        $this->assertNotContains('BR|DARAT', $codes);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), 'nominatim.openstreetmap.org/reverse') && $r->hasHeader('User-Agent'));
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/destination/domestic-destination') && $r['search'] === 'Jongaya Tamalate');
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/calculate/domestic-cost') && (int) $r['destination'] === 55555 && (int) $r['weight'] === 1000);
    }

    public function test_remembered_destination_skips_geocoding_and_search(): void
    {
        $product = $this->stockedProduct(10, ['price' => 20800, 'weight_grams' => 50]);

        $this->postJson(route('shipping.estimate'), [
            'product_id' => $product->id, 'qty' => 1,
            'courier_destination_id' => 55555, 'province' => 'Sulawesi Selatan', 'city' => 'Makassar', 'district' => 'Tamalate', 'subdistrict' => 'Jongaya',
        ])->assertOk()->assertJsonPath('destination.courier_destination_id', 55555)->assertJsonPath('quotes.0.provider_code', 'JNT');

        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), 'nominatim') || str_contains($r->url(), '/destination/'));
    }

    public function test_region_dropdown_and_heavy_product_offers_cargo(): void
    {
        $province = Region::create(['type' => 'province', 'code' => '28', 'name' => 'Sulawesi Selatan']);
        $city = Region::create(['type' => 'city', 'code' => '254', 'name' => 'Kota Makassar', 'parent_id' => $province->id]);
        $district = Region::create(['type' => 'district', 'code' => '3500', 'name' => 'Tamalate', 'parent_id' => $city->id]);
        $sub = Region::create(['type' => 'subdistrict', 'code' => '55555', 'name' => 'Jongaya', 'parent_id' => $district->id, 'postal_code' => '90223']);

        // Baterai 60 kg (kargo) → Buana Raya & Indah tampil, kurir reguler tidak.
        $battery = $this->stockedProduct(10, ['price' => 15_000_000, 'weight_grams' => 60000, 'requires_freight' => true, 'length_cm' => 60, 'width_cm' => 40, 'height_cm' => 20]);

        $res = $this->postJson(route('shipping.estimate'), ['product_id' => $battery->id, 'subdistrict_id' => $sub->id])->assertOk()->json();

        $this->assertSame('Jongaya, Tamalate, Kota Makassar, Sulawesi Selatan, 90223', $res['destination']['label']);
        $codes = array_map(fn ($q) => $q['provider_code'].'|'.$q['service_code'], $res['quotes']);
        $this->assertContains('BR|DARAT', $codes);
        $this->assertContains('INDAH|UDARA', $codes);
        $this->assertContains('cargo|freight', $codes);
        $this->assertNotContains('JNE|REG', $codes);
        $br = collect($res['quotes'])->firstWhere('provider_code', 'BR');
        $this->assertEquals(60 * 6000, $br['cost']); // Makassar min 10 kg × 6.000
        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), '/calculate/domestic-cost'));
    }

    public function test_indah_fallback_and_validation(): void
    {
        $product = $this->stockedProduct(10, ['price' => 20800, 'weight_grams' => 50]);

        // Hanya provinsi/kota (integrasi kurir nonaktif) → Indah tetap dihitung.
        config(['services.rajaongkir.enabled' => false]);
        $this->postJson(route('shipping.estimate'), ['product_id' => $product->id, 'province' => 'Sulawesi Selatan', 'city' => 'Makassar'])
            ->assertOk()->assertJsonPath('quotes.0.provider_code', 'INDAH');
        $this->getJson(route('shipping.indah-cities'))->assertOk()->assertJsonFragment(['Sulawesi Selatan' => ['Makassar']]);

        // Tanpa tujuan sama sekali → 422 dengan pesan ramah.
        $this->postJson(route('shipping.estimate'), ['product_id' => $product->id])->assertUnprocessable();

        // Halaman produk memuat tombolnya.
        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('Berapa ongkir ke lokasi saya?')->assertSee('api\/ongkir\/estimasi', false); // URL di dalam @js → garis miring ter-escape
    }
}
