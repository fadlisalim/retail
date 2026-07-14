<?php

namespace Tests\Feature;

use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAddWithoutVariantTest extends TestCase
{
    use RefreshDatabase;

    /** Adding a product with no variant must not throw "Undefined array key variant_id". */
    public function test_add_to_cart_without_variant_id(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(10);

        // Note: no 'variant_id' key at all, mimicking a quick add-to-cart form.
        $res = $this->post('/keranjang', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');
        $this->assertEquals(1, app(CartService::class)->current()->items()->count());

        // And the cart page renders fine afterwards.
        $this->get('/keranjang')->assertStatus(200);
    }
}
