<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\WarehouseStock;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\SettingService;
use App\Services\Shipping\ShippingQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function quote(float $cost = 50000): ShippingQuote
    {
        return new ShippingQuote(
            providerCode: 'JNE', serviceCode: 'REG', label: 'JNE Reguler', type: 'regular',
            cost: $cost, packingFee: 0, handlingFee: 0, insuranceFee: 0,
            billableWeightGrams: 10000, confirmed: true,
        );
    }

    private function checkoutData(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Budi', 'customer_email' => 'budi@test.id', 'customer_phone' => '628111',
            'recipient_name' => 'Budi', 'province' => 'DKI Jakarta', 'city' => 'Jakarta',
            'address_line' => 'Jl. Test 1', 'payment_method' => 'manual_transfer',
            'idempotency_key' => 'idem-123',
        ], $overrides);
    }

    public function test_checkout_recomputes_totals_on_the_server(): void
    {
        // PPN is off by default now; enable it explicitly to exercise the tax path.
        $settings = app(SettingService::class);
        $settings->set('tax.enabled', true, 'boolean', 'tax');
        $settings->set('tax.ppn_percent', 11, 'integer', 'tax');

        $customer = $this->customer();
        $this->actingAs($customer);
        $product = $this->stockedProduct(10, ['price' => 1000000, 'is_taxable' => true]);

        $cart = app(CartService::class)->current();
        app(CartService::class)->addItem($product, null, 2);

        $order = app(CheckoutService::class)->place($cart->fresh(), $this->checkoutData(), $this->quote());

        $this->assertEquals(2000000, $order->items_subtotal);
        $this->assertEquals(220000, $order->tax_amount);          // 11% PPN
        $this->assertEquals(2270000, $order->grand_total);        // subtotal + 50k shipping + tax
        $this->assertCount(1, $order->items);
        $this->assertEquals(1000000, $order->items->first()->unit_price); // taken from product, not client
    }

    /**
     * Barang bekas/sisa proyek: checkbox wajib di checkout ("...dan konfirmasi
     * kondisi produk") mengesahkan kondisi semua item — pembeli jalur
     * mini-cart → Checkout tidak lagi tertolak "harus menyetujui kondisi".
     */
    public function test_the_checkout_agreement_acknowledges_used_item_conditions(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer);
        $product = $this->stockedProduct(5, ['price' => 170000, 'condition' => 'used']);
        $this->assertTrue($product->requiresConditionAck());

        $cart = app(CartService::class)->current();
        app(CartService::class)->addItem($product, null, 1);
        $this->assertFalse((bool) $cart->fresh()->items->first()->condition_acknowledged);

        $order = app(CheckoutService::class)->place($cart->fresh(), $this->checkoutData(), $this->quote());

        $this->assertNotNull($order->id);
        $this->assertTrue((bool) $order->items()->exists());
    }

    public function test_double_submit_is_idempotent(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer);
        $product = $this->stockedProduct(10, ['price' => 500000]);

        $cart = app(CartService::class)->current();
        app(CartService::class)->addItem($product, null, 1);

        $service = app(CheckoutService::class);
        $data = $this->checkoutData(['idempotency_key' => 'same-key']);

        $first = $service->place($cart->fresh(), $data, $this->quote());
        $second = $service->place($cart->fresh(), $data, $this->quote());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_checkout_page_shows_pickup_address_and_maps_link(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer);
        $product = $this->stockedProduct(10, ['price' => 1000000]);
        app(CartService::class)->addItem($product, null, 1);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('Rekasurya Eco Building', false)
            ->assertSee('Lihat di Google Maps')
            ->assertSee('google.com/maps', false);
    }

    public function test_guest_cannot_access_checkout(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('login'));
    }

    public function test_checkout_store_requires_a_saved_address(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(5, ['price' => 100000]);
        app(CartService::class)->addItem($product, null, 1);

        $this->post(route('checkout.store'), [
            'customer_name' => 'A', 'customer_email' => 'a@a.id', 'customer_phone' => '628',
            'shipping_provider' => 'JNE', 'shipping_service' => 'REG', 'payment_method' => 'manual_transfer',
            'idempotency_key' => 'k1', 'agree_terms' => '1',
        ])->assertSessionHasErrors('address_id');
    }

    public function test_cannot_checkout_with_another_users_address(): void
    {
        $other = $this->customer();
        $othersAddress = CustomerAddress::create([
            'user_id' => $other->id, 'label' => 'Rumah', 'recipient_name' => 'X', 'phone' => '628',
            'province' => 'JAWA BARAT', 'city' => 'Bandung', 'address_line' => 'Jl. X',
        ]);

        $this->actingAs($this->customer());
        $product = $this->stockedProduct(5, ['price' => 100000]);
        app(CartService::class)->addItem($product, null, 1);

        $this->post(route('checkout.store'), [
            'customer_name' => 'A', 'customer_email' => 'a@a.id', 'customer_phone' => '628',
            'address_id' => $othersAddress->id,
            'shipping_provider' => 'JNE', 'shipping_service' => 'REG', 'payment_method' => 'manual_transfer',
            'idempotency_key' => 'k2', 'agree_terms' => '1',
        ])->assertSessionHasErrors('address_id');
    }

    public function test_checkout_reserves_stock(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer);
        $product = $this->stockedProduct(10, ['price' => 100000]);

        $cart = app(CartService::class)->current();
        app(CartService::class)->addItem($product, null, 3);
        app(CheckoutService::class)->place($cart->fresh(), $this->checkoutData(), $this->quote());

        $stock = WarehouseStock::where('product_id', $product->id)->first();
        $this->assertEquals(7, $stock->quantity_available); // 10 - 3 held
        $this->assertEquals(3, $stock->quantity_reserved);
    }
}
