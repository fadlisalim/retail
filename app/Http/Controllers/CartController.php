<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartCalculator;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CartCalculator $calculator,
        private readonly CouponService $coupons,
    ) {
    }

    public function index(): View
    {
        $cart = $this->cart->current()->load(['items.product.brand', 'items.variant', 'savedItems.product']);

        // A product removed from the catalogue (e.g. deleted after a reseed) leaves
        // orphaned cart rows. Drop them so the cart never dereferences a missing
        // product and stale prices can't linger.
        $orphanIds = $cart->items->concat($cart->savedItems)
            ->filter(fn ($i) => ! $i->product)
            ->pluck('id');
        if ($orphanIds->isNotEmpty()) {
            CartItem::whereIn('id', $orphanIds)->delete();
            $cart->setRelation('items', $cart->items->reject(fn ($i) => ! $i->product)->values());
            $cart->setRelation('savedItems', $cart->savedItems->reject(fn ($i) => ! $i->product)->values());
        }

        $totals = $this->calculator->calculate($cart);

        // Split buyable vs quotation-only items for a clear checkout path (spec §14).
        $quotationItems = $cart->items->filter(fn ($i) => $i->product?->requires_quotation);

        return view('storefront.cart', [
            'cart' => $cart,
            'totals' => $totals,
            'quotationItems' => $quotationItems,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        // variant_id is nullable, so it's absent from $data when the product has no
        // variant (e.g. a quick add-to-cart). Coalesce to null instead of indexing.
        $variantId = $data['variant_id'] ?? null;
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;

        $this->cart->addItem($product, $variant, (int) $data['quantity']);

        // AJAX "+ Keranjang" gets the mini-cart payload to open the drawer in place.
        if ($request->expectsJson()) {
            return response()->json($this->miniPayload());
        }

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout.index');
        }

        return back()->with('success', 'Produk ditambahkan ke keranjang.');
    }

    /** JSON snapshot of the cart for the mini-cart drawer (opened from the icon). */
    public function mini(): JsonResponse
    {
        return response()->json($this->miniPayload());
    }

    /** @return array{count:int,subtotal_formatted:string,items:array} */
    private function miniPayload(): array
    {
        $cart = $this->cart->current()->load(['items.product', 'items.variant']);
        $buyable = $this->calculator->buyableItems($cart);

        return [
            'count' => $this->cart->count(),
            'subtotal_formatted' => rupiah($buyable->sum(fn ($i) => $i->currentUnitPrice() * $i->quantity)),
            'items' => $buyable->map(fn ($i) => [
                'id' => (int) $i->id,
                'name' => $i->product->name,
                'variant' => $i->variant?->name,
                'image' => $i->product->primaryImageUrl(),
                'qty' => (int) $i->quantity,
                'line_formatted' => rupiah($i->currentUnitPrice() * $i->quantity),
                'url' => route('products.show', $i->product->slug),
            ])->values()->all(),
        ];
    }

    public function update(Request $request, CartItem $item): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($item);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:9999']]);
        $requested = (int) $data['quantity'];

        $applied = $this->cart->updateQuantity($item, $requested);

        // Clamp feedback (min/max purchase). Only when the item wasn't removed.
        $notice = null;
        if ($requested >= 1 && $applied !== $requested) {
            $unit = $item->product?->unit ?: 'pcs';
            $notice = $applied < $requested
                ? "Jumlah disesuaikan ke maksimum {$applied} {$unit}."
                : "Jumlah disesuaikan ke minimum {$applied} {$unit}.";
        }

        if ($request->expectsJson()) {
            return response()->json($this->cartStatePayload($notice));
        }

        return back()->with($notice ? 'warning' : 'success', $notice ?: 'Keranjang diperbarui.');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($item);
        $this->cart->removeItem($item);

        if ($request->expectsJson()) {
            return response()->json($this->cartStatePayload('Produk dihapus dari keranjang.'));
        }

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }

    public function saveForLater(Request $request, CartItem $item): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($item);
        $this->cart->saveForLater($item, true);

        if ($request->expectsJson()) {
            return response()->json($this->cartStatePayload('Produk disimpan untuk nanti.'));
        }

        return back()->with('success', 'Produk disimpan untuk nanti.');
    }

    public function moveToCart(Request $request, CartItem $item): RedirectResponse|JsonResponse
    {
        $this->authorizeItem($item);
        $this->cart->saveForLater($item, false);

        if ($request->expectsJson()) {
            return response()->json($this->cartStatePayload('Produk dipindah ke keranjang.'));
        }

        return back()->with('success', 'Produk dipindah ke keranjang.');
    }

    /**
     * Full server-recomputed cart state for AJAX mutations on the cart page:
     * per-line totals, the summary figures, and the badge count. All money is
     * formatted server-side so the client never computes prices.
     */
    private function cartStatePayload(?string $notice = null): array
    {
        $cart = $this->cart->current()->load(['items.product', 'items.variant']);
        $totals = $this->calculator->calculate($cart);
        $buyable = $this->calculator->buyableItems($cart);

        return [
            'count' => $this->cart->count(),
            'notice' => $notice,
            'empty' => $buyable->isEmpty(),
            'lines' => $buyable->mapWithKeys(fn ($i) => [(string) $i->id => [
                'quantity' => (int) $i->quantity,
                'line_formatted' => rupiah($i->currentUnitPrice() * $i->quantity),
            ]])->all(),
            'summary' => [
                'item_count' => $totals->itemCount(),
                'subtotal' => rupiah($totals->itemsSubtotal),
                'product_discount' => $totals->productDiscount > 0 ? rupiah($totals->productDiscount) : null,
                'coupon_discount' => $totals->couponDiscount > 0 ? rupiah($totals->couponDiscount) : null,
                'tax' => $totals->taxAmount > 0 ? rupiah($totals->taxAmount) : null,
                'grand_total' => rupiah($totals->grandTotal),
            ],
        ];
    }

    public function acknowledge(CartItem $item): RedirectResponse
    {
        $this->authorizeItem($item);
        $this->cart->acknowledgeCondition($item);

        return back()->with('success', 'Persetujuan kondisi produk dicatat.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['coupon_code' => ['required', 'string', 'max:50']]);

        $cart = $this->cart->current();
        $totals = $this->calculator->calculate($cart);
        $result = $this->coupons->evaluate($data['coupon_code'], $totals->itemsSubtotal, $cart->user_id);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        $this->cart->setCoupon($result['coupon']->code);

        return back()->with('success', 'Voucher berhasil diterapkan.');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->setCoupon(null);

        return back()->with('success', 'Voucher dilepas.');
    }

    public function note(Request $request): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->cart->setNote($data['note'] ?? null);

        return back()->with('success', 'Catatan disimpan.');
    }

    /** Ensure the item belongs to the current visitor's cart. */
    private function authorizeItem(CartItem $item): void
    {
        $current = $this->cart->current();
        abort_unless($item->cart_id === $current->id, 403);
    }
}
