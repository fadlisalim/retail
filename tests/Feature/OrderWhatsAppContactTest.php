<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\CustomerAddress;
use App\Models\IndahCargoRate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\CartService;
use Database\Seeders\IndahCargoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nomor WA pembeli dari checkout dipakai tim untuk kontak: disimpan dalam
 * format 62…, dilengkapi ke profil bila kosong, dan muncul sebagai tombol
 * WhatsApp / WA Chat di halaman pesanan admin.
 */
class OrderWhatsAppContactTest extends TestCase
{
    use RefreshDatabase;

    private function sales(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-sales')->first());

        return $user;
    }

    private function order(string $phone): Order
    {
        return Order::create([
            'order_number' => 'ORD-WA-'.Str::upper(Str::random(4)),
            'public_token' => Str::uuid(),
            'customer_name' => 'Budi',
            'customer_email' => 'budi@test.id',
            'customer_phone' => $phone,
            'status' => 'awaiting_payment',
            'payment_status' => PaymentStatus::Unpaid->value,
            'items_subtotal' => 1_000_000,
            'tax_amount' => 0,
            'grand_total' => 1_000_000,
        ]);
    }

    public function test_admin_order_page_offers_whatsapp_and_wa_chat_buttons(): void
    {
        $order = $this->order('0812-3456-7890');

        $this->actingAs($this->sales())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee(route('admin.wachat.index', ['phone' => '6281234567890']), false)
            ->assertSee('Buka WhatsApp');

        $this->actingAs($this->sales())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('https://wa.me/6281234567890', false);
    }

    public function test_checkout_normalizes_the_phone_and_backfills_an_empty_profile(): void
    {
        $this->seed(ShippingSeeder::class);
        $this->seed(IndahCargoSeeder::class);
        $cities = IndahCargoRate::citiesByProvince();
        $province = array_key_first($cities);
        $city = $cities[$province][0];

        $customer = $this->customer(['whatsapp' => null, 'phone' => null]);
        $address = CustomerAddress::create([
            'user_id' => $customer->id, 'label' => 'Rumah', 'recipient_name' => 'Tester', 'phone' => '62811',
            'province' => $province, 'city' => $city, 'address_line' => 'Jl. Uji 1', 'is_default' => true,
        ]);
        $this->actingAs($customer);

        $product = $this->stockedProduct(5, ['price' => 170000, 'weight_grams' => 8000]);
        app(CartService::class)->addItem($product, null, 1);

        $options = $this->postJson(route('checkout.shipping'), ['province' => $province, 'city' => $city])->assertOk()->json();
        $first = collect($options['options'] ?? $options['quotes'] ?? $options)->first();

        $this->post(route('checkout.store'), [
            'customer_name' => 'Tester', 'customer_email' => $customer->email, 'customer_phone' => '0812 3456 7890',
            'address_id' => $address->id,
            'shipping_provider' => $first['provider'] ?? $first['provider_code'] ?? '',
            'shipping_service' => $first['service'] ?? $first['service_code'] ?? '',
            'payment_method' => 'manual_transfer',
            'agree_terms' => '1',
            'idempotency_key' => 'wa-'.uniqid(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('6281234567890', $order->customer_phone);
        $this->assertSame('6281234567890', $customer->fresh()->whatsapp);
        $this->assertSame('6281234567890', $customer->fresh()->phone);
    }

    /** Nomor di profil hasil registrasi tidak ditimpa oleh nomor lain di checkout. */
    public function test_checkout_does_not_overwrite_an_existing_profile_number(): void
    {
        $this->seed(ShippingSeeder::class);
        $this->seed(IndahCargoSeeder::class);
        $cities = IndahCargoRate::citiesByProvince();
        $province = array_key_first($cities);
        $city = $cities[$province][0];

        $customer = $this->customer(['whatsapp' => '628111111111', 'phone' => '628111111111']);
        $address = CustomerAddress::create([
            'user_id' => $customer->id, 'label' => 'Rumah', 'recipient_name' => 'Tester', 'phone' => '62811',
            'province' => $province, 'city' => $city, 'address_line' => 'Jl. Uji 1', 'is_default' => true,
        ]);
        $this->actingAs($customer);

        // Form checkout terisi otomatis dari nomor WA profil.
        $product = $this->stockedProduct(5, ['price' => 170000, 'weight_grams' => 8000]);
        app(CartService::class)->addItem($product, null, 1);
        $this->get('/checkout')->assertOk()->assertSee('value="628111111111"', false);

        $options = $this->postJson(route('checkout.shipping'), ['province' => $province, 'city' => $city])->assertOk()->json();
        $first = collect($options['options'] ?? $options['quotes'] ?? $options)->first();

        $this->post(route('checkout.store'), [
            'customer_name' => 'Tester', 'customer_email' => $customer->email, 'customer_phone' => '08222222222',
            'address_id' => $address->id,
            'shipping_provider' => $first['provider'] ?? $first['provider_code'] ?? '',
            'shipping_service' => $first['service'] ?? $first['service_code'] ?? '',
            'payment_method' => 'manual_transfer',
            'agree_terms' => '1',
            'idempotency_key' => 'wa-'.uniqid(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('628222222222', Order::latest('id')->firstOrFail()->customer_phone);
        $this->assertSame('628111111111', $customer->fresh()->whatsapp);
    }
}
