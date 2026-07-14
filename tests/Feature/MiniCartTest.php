<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiniCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_add_returns_mini_cart_payload(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(10);

        $res = $this->postJson('/keranjang', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $res->assertOk()
            ->assertJsonStructure(['count', 'subtotal_formatted', 'items' => [['name', 'image', 'qty', 'line_formatted', 'url']]])
            ->assertJsonPath('count', 2)
            ->assertJsonPath('items.0.qty', 2);
    }

    public function test_mini_endpoint_returns_current_cart(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(10);
        $this->postJson('/keranjang', ['product_id' => $product->id, 'quantity' => 1]);

        $this->getJson('/keranjang/mini')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.name', $product->name);
    }
}
