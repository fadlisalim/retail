<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\IndahCargoRate;
use App\Models\Order;
use App\Services\CartService;
use App\Services\Shipping\ShippingDestination;
use App\Services\ShippingService;
use Database\Seeders\IndahCargoSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ongkir kurir reguler (JNE, J&T, …) via RajaOngkir/Komerce: tarif dari API
 * untuk paket ringan, di-cache, dilewati untuk barang kargo/berat, alamat lama
 * dicari ID kelurahannya sekali lalu diingat, dan checkout menerima pilihannya.
 */
class RajaOngkirShippingTest extends TestCase
{
    use RefreshDatabase;

    private const DEST_ID = 17473;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.rajaongkir.enabled' => true,
            'services.rajaongkir.api_key' => 'test-key',
            'services.rajaongkir.origin_id' => 17001,
            'services.rajaongkir.couriers' => 'jne:jnt',
            'services.rajaongkir.base_url' => 'https://rajaongkir.komerce.id/api/v1',
        ]);
        $this->fakeApi();
    }

    private function fakeApi(): void
    {
        Http::fake([
            'rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response([
                'meta' => ['message' => 'Success', 'code' => 200, 'status' => 'success'],
                'data' => [
                    ['name' => 'Jalur Nugraha Ekakurir (JNE)', 'code' => 'jne', 'service' => 'REG', 'description' => 'Layanan Reguler', 'cost' => 12000, 'etd' => '1-2 day'],
                    ['name' => 'J&T Express', 'code' => 'jnt', 'service' => 'EZ', 'description' => 'Regular Service', 'cost' => 11000, 'etd' => '2 day'],
                ],
            ]),
            'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
                'meta' => ['message' => 'Success', 'code' => 200, 'status' => 'success'],
                'data' => [
                    ['id' => 99, 'label' => 'COBLONG, KAB. BANDUNG BARAT, JAWA BARAT, 40559', 'province_name' => 'JAWA BARAT', 'city_name' => 'KAB. BANDUNG BARAT', 'district_name' => 'COBLONG', 'subdistrict_name' => 'LEMBANG', 'zip_code' => '40559'],
                    ['id' => self::DEST_ID, 'label' => 'DAGO, COBLONG, BANDUNG, JAWA BARAT, 40135', 'province_name' => 'JAWA BARAT', 'city_name' => 'BANDUNG', 'district_name' => 'COBLONG', 'subdistrict_name' => 'DAGO', 'zip_code' => '40135'],
                ],
            ]),
        ]);
    }

    private function cartWith(array $productOverrides = []): Cart
    {
        $product = $this->stockedProduct(10, array_merge([
            'price' => 200_000, 'weight_grams' => 1000, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10,
        ], $productOverrides));
        $cart = app(CartService::class)->current();
        app(CartService::class)->addItem($product, null, 1);

        return $cart->fresh(['items.product', 'items.variant']);
    }

    private function address(array $overrides = []): CustomerAddress
    {
        return CustomerAddress::create(array_merge([
            'user_id' => auth()->id(), 'label' => 'Rumah', 'recipient_name' => 'Tester', 'phone' => '62811',
            'province' => 'Jawa Barat', 'city' => 'BANDUNG', 'district' => 'Coblong', 'subdistrict' => 'Dago',
            'postal_code' => '40135', 'address_line' => 'Jl. Uji 1', 'is_default' => true,
            'courier_destination_id' => self::DEST_ID, 'courier_destination_label' => 'DAGO, COBLONG, BANDUNG',
        ], $overrides));
    }

    public function test_light_carts_get_courier_quotes_from_the_api(): void
    {
        $this->actingAs($this->customer());
        $cart = $this->cartWith();
        $address = $this->address();

        $quotes = collect(app(ShippingService::class)->quotesFor($cart, 'Jawa Barat', 'BANDUNG', ShippingDestination::fromAddress($address)));

        $jne = $quotes->first(fn ($q) => $q->providerCode === 'JNE' && $q->serviceCode === 'REG');
        $jnt = $quotes->first(fn ($q) => $q->providerCode === 'JNT' && $q->serviceCode === 'EZ');
        $this->assertNotNull($jne);
        $this->assertNotNull($jnt);
        $this->assertEquals(12000, $jne->cost);
        $this->assertSame('JNE — REG (Layanan Reguler)', $jne->label);
        $this->assertSame('1-2 hari', $jne->estimatedDays);
        $this->assertSame('2 hari', $jnt->estimatedDays);
        $this->assertTrue($jne->confirmed);
        // Termurah lebih dulu.
        $this->assertTrue($quotes->search(fn ($q) => $q === $jnt) < $quotes->search(fn ($q) => $q === $jne));

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/calculate/domestic-cost')
            && $r->hasHeader('key', 'test-key')
            && (int) $r['origin'] === 17001 && (int) $r['destination'] === self::DEST_ID
            && (int) $r['weight'] === 1000 && $r['courier'] === 'jne:jnt');
        // Alamat sudah punya ID → pencarian tidak dipanggil.
        Http::assertNotSent(fn (ClientRequest $r) => str_contains($r->url(), '/destination/'));
    }

    public function test_quotes_are_cached_per_destination_and_weight(): void
    {
        $this->actingAs($this->customer());
        $cart = $this->cartWith();
        $dest = ShippingDestination::fromAddress($this->address());

        app(ShippingService::class)->quotesFor($cart, 'Jawa Barat', 'BANDUNG', $dest);
        app(ShippingService::class)->quotesFor($cart, 'Jawa Barat', 'BANDUNG', $dest);
        // findQuote saat submit checkout juga memakai cache — bukan request baru.
        $found = app(ShippingService::class)->findQuote($cart, 'Jawa Barat', 'JNE', 'REG', 'BANDUNG', $dest);

        $this->assertNotNull($found);
        Http::assertSentCount(1);
    }

    public function test_freight_and_heavy_carts_never_call_the_api(): void
    {
        $this->actingAs($this->customer());
        $dest = ShippingDestination::fromAddress($this->address());

        // Panel surya (kargo) → hanya Kargo dikonfirmasi + ambil di gudang.
        $panel = $this->cartWith(['requires_freight' => true, 'weight_grams' => 27100]);
        $quotes = collect(app(ShippingService::class)->quotesFor($panel, 'Jawa Barat', 'BANDUNG', $dest));
        $this->assertNull($quotes->first(fn ($q) => $q->providerCode === 'JNE'));
        $this->assertNotNull($quotes->first(fn ($q) => $q->providerCode === 'cargo'));

        // Baterai 60 kg (bukan flag kargo) → di atas batas kurir reguler.
        $panel->items()->delete();
        $heavy = $this->cartWith(['weight_grams' => 60000]);
        $quotes = collect(app(ShippingService::class)->quotesFor($heavy, 'Jawa Barat', 'BANDUNG', $dest));
        $this->assertNull($quotes->first(fn ($q) => $q->providerCode === 'JNE'));

        Http::assertNothingSent();
    }

    public function test_old_addresses_are_resolved_by_search_and_remembered(): void
    {
        $this->actingAs($this->customer());
        $cart = $this->cartWith();
        $address = $this->address(['courier_destination_id' => null, 'courier_destination_label' => null]);

        $quotes = collect(app(ShippingService::class)->quotesFor($cart, 'Jawa Barat', 'BANDUNG', ShippingDestination::fromAddress($address)));

        $this->assertNotNull($quotes->first(fn ($q) => $q->providerCode === 'JNE'));
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/destination/domestic-destination') && $r['search'] === 'Coblong BANDUNG');
        // Hasil yang kotanya cocok (BANDUNG, bukan KAB. BANDUNG BARAT) yang dipilih dan disimpan.
        $address->refresh();
        $this->assertSame(self::DEST_ID, (int) $address->courier_destination_id);
        $this->assertSame('DAGO, COBLONG, BANDUNG, JAWA BARAT, 40135', $address->courier_destination_label);
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/calculate/domestic-cost') && (int) $r['destination'] === self::DEST_ID);
    }

    public function test_disabled_integration_or_missing_destination_adds_nothing(): void
    {
        $this->actingAs($this->customer());
        $cart = $this->cartWith();
        $address = $this->address();

        // Tanpa destinasi (hanya provinsi/kota) → jalur lama saja.
        $quotes = collect(app(ShippingService::class)->quotesFor($cart, 'Jawa Barat', 'BANDUNG'));
        $this->assertNull($quotes->first(fn ($q) => $q->providerCode === 'JNE'));

        config(['services.rajaongkir.enabled' => false]);
        $quotes = collect(app(ShippingService::class)->quotesFor($cart, 'Jawa Barat', 'BANDUNG', ShippingDestination::fromAddress($address)));
        $this->assertNull($quotes->first(fn ($q) => $q->providerCode === 'JNE'));

        Http::assertNothingSent();
    }

    public function test_api_failure_degrades_to_the_existing_options(): void
    {
        // Stub setUp tetap terpasang untuk host asli — arahkan ke host lain yang menjawab 401.
        config(['services.rajaongkir.base_url' => 'https://ro-down.test/api/v1']);
        Http::fake(['ro-down.test/*' => Http::response(['meta' => ['message' => 'Unauthorized', 'code' => 401]], 401)]);
        $this->actingAs($this->customer());
        $cart = $this->cartWith();

        $quotes = collect(app(ShippingService::class)->quotesFor($cart, 'Jawa Barat', 'BANDUNG', ShippingDestination::fromAddress($this->address())));

        $this->assertNull($quotes->first(fn ($q) => $q->providerCode === 'JNE'));
        $this->assertNotEmpty($quotes); // "ongkir dikonfirmasi" tetap ada — checkout tidak buntu
    }

    public function test_checkout_offers_and_accepts_a_courier_quote(): void
    {
        $this->seed(ShippingSeeder::class);
        $this->seed(IndahCargoSeeder::class);
        $cities = IndahCargoRate::citiesByProvince();
        $province = array_key_first($cities);

        $customer = $this->customer();
        $this->actingAs($customer);
        $address = $this->address(['province' => $province, 'city' => $cities[$province][0]]);
        $this->cartWith();

        $options = $this->postJson(route('checkout.shipping'), ['address_id' => $address->id])->assertOk()->json();
        $jne = collect($options)->first(fn ($o) => $o['provider_code'] === 'JNE' && $o['service_code'] === 'REG');
        $this->assertNotNull($jne, 'Opsi JNE tidak muncul: '.json_encode($options));
        $this->assertEquals(12000, $jne['cost']);
        $this->assertSame('1-2 hari', $jne['estimated_days']);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Tester', 'customer_email' => $customer->email, 'customer_phone' => '62811',
            'address_id' => $address->id,
            'shipping_provider' => 'JNE', 'shipping_service' => 'REG',
            'payment_method' => 'manual_transfer', 'agree_terms' => '1', 'idempotency_key' => 'ro-'.uniqid(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('JNE/REG', $order->shipping_method);
        $this->assertSame('JNE — REG (Layanan Reguler)', $order->shipping_service_name);
        $this->assertEquals(12000, (float) $order->shipping_cost);
        $this->assertTrue((bool) $order->shipping_cost_confirmed);
    }

    public function test_destination_search_endpoint_requires_login_and_returns_matches(): void
    {
        $this->getJson(route('shipping.destinations', ['q' => 'coblong']))->assertUnauthorized();

        $this->actingAs($this->customer())
            ->getJson(route('shipping.destinations', ['q' => 'coblong']))
            ->assertOk()
            ->assertJsonFragment(['id' => self::DEST_ID, 'district' => 'COBLONG', 'postal_code' => '40135']);

        $this->actingAs($this->customer())
            ->getJson(route('shipping.destinations', ['q' => 'co']))
            ->assertUnprocessable();
    }

    public function test_address_form_saves_the_picked_destination(): void
    {
        $this->seed(IndahCargoSeeder::class);
        $cities = IndahCargoRate::citiesByProvince();
        $province = array_key_first($cities);
        $customer = $this->customer();

        $this->actingAs($customer)->get(route('account.addresses.create'))
            ->assertOk()->assertSee('Cari kecamatan / kelurahan');

        $this->actingAs($customer)->post(route('account.addresses.store'), [
            'label' => 'Rumah', 'recipient_name' => 'Tester', 'phone' => '0811', 'province' => $province, 'city' => $cities[$province][0],
            'district' => 'COBLONG', 'subdistrict' => 'DAGO', 'postal_code' => '40135', 'address_line' => 'Jl. Uji 1',
            'courier_destination_id' => self::DEST_ID, 'courier_destination_label' => 'DAGO, COBLONG, BANDUNG, JAWA BARAT, 40135',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('customer_addresses', ['user_id' => $customer->id, 'courier_destination_id' => self::DEST_ID]);
    }
}
