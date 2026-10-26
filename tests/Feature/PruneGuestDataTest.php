<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pengunjung/bot tidak lagi membuat keranjang & wishlist kosong; data tamu lama dibersihkan harian. */
class PruneGuestDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_browsing_does_not_create_empty_carts_or_wishlists(): void
    {
        $product = Product::factory()->create();

        $this->get('/')->assertOk();
        $this->get(route('products.show', $product->slug))->assertOk();
        $this->get(route('cart.index'))->assertOk();

        $this->assertSame(0, Wishlist::count());
        $this->assertSame(0, Cart::whereDoesntHave('allItems')->where('updated_at', '<', now()->subMinute())->count());
        $this->assertLessThanOrEqual(1, Cart::count()); // halaman keranjang boleh membuat 1 keranjang sesi ini
    }

    public function test_prune_removes_old_empty_guest_rows_but_keeps_customer_and_recent_data(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $old = now()->subDays(3);

        $emptyGuest = Cart::create(['session_token' => 'g1']);
        $emptyGuest->forceFill(['updated_at' => $old])->saveQuietly();
        $freshGuest = Cart::create(['session_token' => 'g2']);
        $guestWithItem = Cart::create(['session_token' => 'g3']);
        $guestWithItem->allItems()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price_snapshot' => 1000]);
        $guestWithItem->forceFill(['updated_at' => $old])->saveQuietly();
        $userCart = Cart::create(['user_id' => $user->id]);
        $userCart->forceFill(['updated_at' => now()->subDays(400)])->saveQuietly();

        $emptyWish = Wishlist::create(['session_token' => 'g1']);
        $emptyWish->forceFill(['updated_at' => $old])->saveQuietly();
        RecentlyViewedProduct::create(['session_token' => 'g1', 'product_id' => $product->id, 'viewed_at' => now()->subDays(40)]);
        RecentlyViewedProduct::create(['user_id' => $user->id, 'product_id' => $product->id, 'viewed_at' => now()->subDays(40)]);

        $this->artisan('guests:prune --dry-run')->expectsOutputToContain('Akan dihapus 1 keranjang tamu kosong')->assertSuccessful();
        $this->assertSame(4, Cart::count());

        $this->artisan('guests:prune')->assertSuccessful();

        $this->assertNull(Cart::find($emptyGuest->id));
        $this->assertNotNull(Cart::find($freshGuest->id));
        $this->assertNotNull(Cart::find($guestWithItem->id));
        $this->assertNotNull(Cart::find($userCart->id));
        $this->assertSame(0, Wishlist::count());
        $this->assertSame(1, RecentlyViewedProduct::count());
        $this->assertSame($user->id, RecentlyViewedProduct::value('user_id'));
    }
}
