<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns cart lifecycle: resolves the active cart for a guest (session token) or a
 * logged-in user, mutates line items with stock/min/max validation, and merges a
 * guest cart into the account on login. Stock is validated but NOT decremented
 * here — it is only reserved when the customer enters payment (see StockService).
 */
class CartService
{
    /** Resolve (or create) the active cart for the current request context. */
    public function current(): Cart
    {
        if ($userId = auth()->id()) {
            return Cart::firstOrCreate(
                ['user_id' => $userId],
                ['last_activity_at' => now()],
            );
        }

        $token = session('guest_token');

        return Cart::firstOrCreate(
            ['session_token' => $token, 'user_id' => null],
            ['last_activity_at' => now()],
        );
    }

    /** Merge the guest cart into the user's cart after a successful login. */
    public function mergeGuestIntoUser(int $userId, string $guestToken): void
    {
        $guestCart = Cart::where('session_token', $guestToken)->whereNull('user_id')->first();
        if (! $guestCart) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $userId], ['last_activity_at' => now()]);

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->allItems as $item) {
                $existing = $userCart->allItems()
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->where('saved_for_later', $item->saved_for_later)
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', $item->quantity);
                } else {
                    $item->cart_id = $userCart->id;
                    $item->save();
                }
            }

            if (! $userCart->coupon_code && $guestCart->coupon_code) {
                $userCart->update(['coupon_code' => $guestCart->coupon_code]);
            }

            $guestCart->delete();
        });
    }

    public function addItem(Product $product, ?ProductVariant $variant, int $quantity): CartItem
    {
        if (! $product->is_purchasable || $product->requires_quotation) {
            throw ValidationException::withMessages([
                'product' => 'Produk ini hanya tersedia melalui permintaan penawaran.',
            ]);
        }

        $quantity = max($product->min_purchase, $quantity);
        if ($product->max_purchase) {
            $quantity = min($product->max_purchase, $quantity);
        }

        $this->assertStock($product, $variant, $quantity);

        $cart = $this->current();

        $item = $cart->allItems()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->where('saved_for_later', false)
            ->first();

        if ($item) {
            $newQty = $item->quantity + $quantity;
            if ($product->max_purchase) {
                $newQty = min($product->max_purchase, $newQty);
            }
            $this->assertStock($product, $variant, $newQty);
            $item->update([
                'quantity' => $newQty,
                'unit_price_snapshot' => $variant?->effectivePrice() ?? $product->effectivePrice(),
            ]);

            return $item;
        }

        return $cart->allItems()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
            'unit_price_snapshot' => $variant?->effectivePrice() ?? $product->effectivePrice(),
        ]);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $item->delete();

            return;
        }

        $product = $item->product;
        if ($product->max_purchase) {
            $quantity = min($product->max_purchase, $quantity);
        }
        $quantity = max($product->min_purchase, $quantity);

        $this->assertStock($product, $item->variant, $quantity);
        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function saveForLater(CartItem $item, bool $saved = true): void
    {
        $item->update(['saved_for_later' => $saved]);
    }

    public function acknowledgeCondition(CartItem $item): void
    {
        $item->update(['condition_acknowledged' => true]);
    }

    public function setCoupon(?string $code): void
    {
        $this->current()->update(['coupon_code' => $code]);
    }

    public function setNote(?string $note): void
    {
        $this->current()->update(['note' => $note]);
    }

    /** Total buyable-item quantity, for the header cart badge. */
    public function count(): int
    {
        return (int) $this->current()->items()->sum('quantity');
    }

    public function availableStock(Product $product, ?ProductVariant $variant): int
    {
        return (int) ($variant ? $variant->stock : $product->stock);
    }

    private function assertStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $available = $this->availableStock($product, $variant);

        // Backorder is not allowed: cart quantity can never exceed on-hand stock.
        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'stock' => "Stok tidak mencukupi. Tersedia {$available} {$product->unit}.",
            ]);
        }
    }
}
