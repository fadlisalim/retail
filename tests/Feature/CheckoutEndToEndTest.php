<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\IndahCargoRate;
use App\Models\Order;
use App\Services\CartService;
use Database\Seeders\IndahCargoSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit alur checkout UTUH lewat HTTP (bukan service langsung): halaman →
 * opsi ongkir → submit "Buat Pesanan & Bayar" → pesanan terbentuk. Menyisir
 * jalur controller sungguhan yang tidak tersentuh test service-level —
 * tempat error 500 produksi biasanya bersembunyi.
 */
class CheckoutEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithAddress(): array
    {
        $this->seed(ShippingSeeder::class);
        $this->seed(IndahCargoSeeder::class);

        // Alamat memakai kota pertama yang benar-benar ada tarifnya.
        $cities = IndahCargoRate::citiesByProvince();
        $province = array_key_first($cities);
        $city = $cities[$province][0];

        $customer = $this->customer();
        $address = CustomerAddress::create([
            'user_id' => $customer->id, 'label' => 'Rumah', 'recipient_name' => 'Tester', 'phone' => '62811',
            'province' => $province, 'city' => $city, 'address_line' => 'Jl. Uji 1', 'is_default' => true,
        ]);

        return [$customer, $address];
    }

    public function test_the_full_checkout_flow_places_an_order(): void
    {
        [$customer, $address] = $this->customerWithAddress();
        $this->actingAs($customer);

        $product = $this->stockedProduct(5, ['price' => 170000, 'condition' => 'used', 'weight_grams' => 8000]);
        app(CartService::class)->addItem($product, null, 1);

        // 1. Halaman checkout tampil.
        $this->get('/checkout')->assertOk()->assertSee('Ringkasan Pesanan');

        // 2. Opsi ongkir untuk alamat itu tersedia.
        $options = $this->postJson(route('checkout.shipping'), ['province' => $address->province, 'city' => $address->city])
            ->assertOk()->json();
        $quotes = $options['options'] ?? $options['quotes'] ?? $options;
        $this->assertNotEmpty($quotes, 'Tidak ada opsi pengiriman: '.json_encode($options));
        $first = collect($quotes)->first();

        // 3. Submit pesanan dengan opsi pertama (apa pun itu, termasuk pickup).
        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Tester', 'customer_email' => $customer->email, 'customer_phone' => '62811',
            'address_id' => $address->id,
            'shipping_provider' => $first['provider'] ?? $first['provider_code'] ?? '',
            'shipping_service' => $first['service'] ?? $first['service_code'] ?? '',
            'payment_method' => 'manual_transfer',
            'agree_terms' => '1',
            'idempotency_key' => 'e2e-'.uniqid(),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertNotNull($order, 'Pesanan tidak terbentuk.');
        $this->assertSame(170000.0, (float) $order->items_subtotal);
    }

    /** Jalur ambil di gudang (ongkir Rp 0) juga harus tembus. */
    public function test_pickup_at_warehouse_checkout_places_an_order(): void
    {
        [$customer, $address] = $this->customerWithAddress();
        $this->actingAs($customer);

        $product = $this->stockedProduct(5, ['price' => 250000, 'weight_grams' => 8000]);
        app(CartService::class)->addItem($product, null, 1);

        $options = $this->postJson(route('checkout.shipping'), ['province' => $address->province, 'city' => $address->city])->assertOk()->json();
        $quotes = collect($options['options'] ?? $options['quotes'] ?? $options);
        $pickup = $quotes->first(fn ($q) => ($q['type'] ?? '') === 'pickup'
            || str_contains(mb_strtolower($q['label'] ?? ''), 'ambil'));

        if (! $pickup) {
            $this->markTestSkipped('Opsi pickup tidak ditawarkan untuk keranjang ini.');
        }

        $this->post(route('checkout.store'), [
            'customer_name' => 'Tester', 'customer_email' => $customer->email, 'customer_phone' => '62811',
            'address_id' => $address->id,
            'shipping_provider' => $pickup['provider'] ?? $pickup['provider_code'] ?? '',
            'shipping_service' => $pickup['service'] ?? $pickup['service_code'] ?? '',
            'payment_method' => 'manual_transfer',
            'agree_terms' => '1',
            'idempotency_key' => 'e2e-pickup-'.uniqid(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, Order::count());
        $this->assertEquals(0, (float) Order::first()->shipping_cost);
    }
}
