<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartOrphanedProductTest extends TestCase
{
    use RefreshDatabase;

    /** A cart/saved item whose product was removed must not 500 the cart page. */
    public function test_cart_page_prunes_items_with_deleted_product(): void
    {
        $this->actingAs($this->customer());
        $svc = app(CartService::class);

        $keep = $this->stockedProduct(10);
        $gone = $this->stockedProduct(10);
        $goneSaved = $this->stockedProduct(10);

        $svc->addItem($keep, null, 1);
        $orphanItem = $svc->addItem($gone, null, 2);
        $savedItem = $svc->addItem($goneSaved, null, 1);
        $svc->saveForLater($savedItem, true);

        // Products vanish from the catalogue (e.g. after a reseed).
        Product::whereKey([$gone->id, $goneSaved->id])->delete();

        $this->get('/keranjang')->assertStatus(200);

        // Orphaned rows are pruned; only the surviving product remains.
        $this->assertDatabaseMissing('cart_items', ['id' => $orphanItem->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $savedItem->id]);
        $this->assertEquals(1, CartItem::count());
    }
}
