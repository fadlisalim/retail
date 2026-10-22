<?php

namespace App\View\Composers;

use App\Models\Article;
use App\Models\Category;
use App\Models\OrderDocumentation;
use App\Models\Product;
use App\Services\CartService;
use App\Services\ComparisonService;
use App\Services\SettingService;
use App\Services\WishlistService;
use App\Support\CategoryVisibility;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Injects the chrome data every storefront page needs — navigation categories
 * (cached), live cart/wishlist/compare counts, and WhatsApp/company settings —
 * so individual controllers don't have to. Included partials inherit these.
 */
class StorefrontComposer
{
    public function __construct(
        private readonly CartService $cart,
        private readonly WishlistService $wishlist,
        private readonly ComparisonService $comparison,
        private readonly SettingService $settings,
    ) {}

    public function compose(View $view): void
    {
        $view->with([
            'navCategories' => $this->navCategories(),
            'cartCount' => $this->cart->count(),
            'wishlistCount' => $this->wishlist->count(),
            'compareCount' => $this->comparison->count(),
            'siteSettings' => $this->settings,
            'whatsappEnabled' => $this->settings->whatsappEnabled(),
            'whatsappNumber' => $this->settings->whatsappNumber(),
            'navHas' => $this->navHas(),
        ]);
    }

    /**
     * Halaman mana yang punya isi — link ke halaman kosong (Promo tanpa produk promo,
     * Dokumentasi tanpa foto publik, dst.) disembunyikan dari header & footer.
     *
     * @return array{promo: bool, clearance: bool, articles: bool, documentation: bool}
     */
    private function navHas(): array
    {
        return Cache::remember('nav:has', 600, fn () => [
            'promo' => Product::published()->where(fn ($q) => $q->where('is_promo', true)->orWhereNotNull('sale_price'))->exists(),
            'clearance' => Product::published()->where(fn ($q) => $q->where('is_clearance', true)->orWhere('condition', '!=', 'new'))->exists(),
            'articles' => Article::published()->exists(),
            'documentation' => OrderDocumentation::where('is_public', true)->exists(),
        ]);
    }

    /**
     * Root categories with their active children for the mega-menu. Kept as a live
     * (indexed) query rather than a persisted cache — caching hydrated Eloquent
     * models in a serialized store (database/file/redis) risks incomplete-object
     * errors on unserialize. Cache the rendered fragment instead if needed later.
     */
    private function navCategories()
    {
        return CategoryVisibility::filter(Category::active()->roots()
            ->with(['activeChildren'])
            ->orderBy('sort_order')
            ->get());
    }
}
