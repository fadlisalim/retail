<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Support\Str;

class WishlistService
{
    public function current(): Wishlist
    {
        if ($userId = auth()->id()) {
            return Wishlist::firstOrCreate(['user_id' => $userId]);
        }

        return Wishlist::firstOrCreate(
            ['session_token' => session('guest_token'), 'user_id' => null],
        );
    }

    public function toggle(Product $product): bool
    {
        $wishlist = $this->current();
        $existing = $wishlist->items()->where('product_id', $product->id)->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $wishlist->items()->create(['product_id' => $product->id]);

        return true;
    }

    public function has(Product $product): bool
    {
        return $this->current()->items()->where('product_id', $product->id)->exists();
    }

    public function count(): int
    {
        return $this->current()->items()->count();
    }

    public function mergeGuestIntoUser(int $userId, string $guestToken): void
    {
        $guest = Wishlist::where('session_token', $guestToken)->whereNull('user_id')->first();
        if (! $guest) {
            return;
        }

        $userWishlist = Wishlist::firstOrCreate(['user_id' => $userId]);

        foreach ($guest->items as $item) {
            $userWishlist->items()->firstOrCreate(['product_id' => $item->product_id]);
        }

        $guest->delete();
    }

    /** Enable a public share URL and return its token. */
    public function enableSharing(): string
    {
        $wishlist = $this->current();
        if (! $wishlist->share_token) {
            $wishlist->update(['share_token' => Str::random(32), 'is_public' => true]);
        } elseif (! $wishlist->is_public) {
            $wishlist->update(['is_public' => true]);
        }

        return $wishlist->share_token;
    }
}
