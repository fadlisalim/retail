<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutDeadItemReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_and_cart_survive_a_soft_deleted_product(): void
    {
        $customer = $this->customer();
        CustomerAddress::create([
            'user_id' => $customer->id, 'label' => 'Rumah', 'recipient_name' => 'T', 'phone' => '628',
            'province' => 'JAWA BARAT', 'city' => 'Bandung', 'address_line' => 'Jl. X', 'is_default' => true,
        ]);
        $this->actingAs($customer);

        $alive = $this->stockedProduct(5, ['price' => 100000]);
        $dead = $this->stockedProduct(5, ['price' => 200000]);
        app(CartService::class)->addItem($alive, null, 1);
        app(CartService::class)->addItem($dead, null, 1);
        $dead->delete(); // konversi seeder menghapus produk lama

        $this->get('/keranjang')->assertOk();
        $this->get('/keranjang/mini')->assertOk();
        $this->get('/checkout')->assertOk();
    }
}
