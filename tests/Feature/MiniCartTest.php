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

    public function test_ajax_quantity_update_returns_recomputed_state(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(10);
        $this->postJson('/keranjang', ['product_id' => $product->id, 'quantity' => 1]);
        $item = \App\Models\CartItem::first();

        $res = $this->patchJson("/keranjang/{$item->id}", ['quantity' => 3]);

        $res->assertOk()
            ->assertJsonStructure(['count', 'empty', 'lines', 'summary' => ['item_count', 'subtotal', 'grand_total']])
            ->assertJsonPath('count', 3)
            ->assertJsonPath('empty', false)
            ->assertJsonPath("lines.{$item->id}.quantity", 3);
    }

    public function test_ajax_remove_marks_cart_empty(): void
    {
        $this->actingAs($this->customer());
        $product = $this->stockedProduct(10);
        $this->postJson('/keranjang', ['product_id' => $product->id, 'quantity' => 1]);
        $item = \App\Models\CartItem::first();

        $this->deleteJson("/keranjang/{$item->id}")
            ->assertOk()
            ->assertJsonPath('empty', true)
            ->assertJsonPath('count', 0);
    }

    public function test_ajax_update_cannot_touch_another_users_item(): void
    {
        $product = $this->stockedProduct(10);
        $this->actingAs($this->customer());
        $this->postJson('/keranjang', ['product_id' => $product->id, 'quantity' => 1]);
        $item = \App\Models\CartItem::first();

        // A different user must not be able to mutate this item.
        $this->actingAs($this->customer())
            ->patchJson("/keranjang/{$item->id}", ['quantity' => 5])
            ->assertForbidden();
    }
}
