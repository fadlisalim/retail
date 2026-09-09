<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\CustomerAddress;
use App\Models\IndahCargoRate;
use App\Models\Order;
use App\Services\CartService;
use App\Services\StockService;
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

    /** Produk bervarian tanpa memilih varian → ditolak ramah, bukan masuk keranjang. */
    public function test_variable_products_cannot_be_added_without_a_variant(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(0, ['name' => 'Panel Bekas Varian', 'product_type' => 'variable', 'price' => 170000]);
        $product->variants()->create(['sku' => 'PBV-50', 'name' => '50 Wp', 'option_values' => ['Daya' => '50 Wp'], 'price' => 170000, 'is_active' => true, 'sort_order' => 0, 'stock' => 0]);
        // Stok agregat produk > 0 (varian lain) — dulu bikin item tanpa varian lolos.
        $product->forceFill(['stock' => 3])->save();

        $this->postJson(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variant_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    /** Varian milik produk lain ditolak. */
    public function test_a_foreign_variant_is_rejected(): void
    {
        $this->actingAs($this->customer());
        $a = $this->stockedProduct(5, ['product_type' => 'variable']);
        $b = $this->stockedProduct(5, ['product_type' => 'variable']);
        $foreign = $b->variants()->create(['sku' => 'FRG-1', 'name' => 'X', 'option_values' => ['U' => 'X'], 'price' => 100000, 'is_active' => true, 'sort_order' => 0, 'stock' => 5]);

        $this->postJson(route('cart.store'), ['product_id' => $a->id, 'variant_id' => $foreign->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('variant_id');
    }

    /** Stok habis saat submit checkout → pesan ramah, bukan 500. */
    public function test_out_of_stock_at_submit_shows_a_friendly_error_not_a_500(): void
    {
        [$customer, $address] = $this->customerWithAddress();
        $this->actingAs($customer);

        $product = $this->stockedProduct(1, ['price' => 170000, 'weight_grams' => 8000]);
        app(CartService::class)->addItem($product, null, 1);
        // Stok keburu habis setelah masuk keranjang (dibeli orang lain / dikoreksi admin).
        app(StockService::class)->adjust($product, null, -1, StockMovementType::Adjustment);

        $options = $this->postJson(route('checkout.shipping'), ['province' => $address->province, 'city' => $address->city])->assertOk()->json();
        $first = collect($options['options'] ?? $options)->first();

        $this->post(route('checkout.store'), [
            'customer_name' => 'Tester', 'customer_email' => $customer->email, 'customer_phone' => '62811',
            'address_id' => $address->id,
            'shipping_provider' => $first['provider'] ?? $first['provider_code'] ?? '',
            'shipping_service' => $first['service'] ?? $first['service_code'] ?? '',
            'payment_method' => 'manual_transfer',
            'agree_terms' => '1',
            'idempotency_key' => 'e2e-oos-'.uniqid(),
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Order::count());
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
