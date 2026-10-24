<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Support\CategoryVisibility;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // Satu query untuk semua banner beranda (sebelumnya 4 query terpisah).
        $banners = Banner::active()
            ->whereIn('position', ['hero', 'grid', 'video', 'quotation'])
            ->orderBy('sort_order')
            ->get()
            ->groupBy('position');

        return view('storefront.home', [
            'heroBanners' => $banners->get('hero', collect()),
            'gridBanners' => $banners->get('grid', collect()),
            'videoBanners' => $banners->get('video', collect()),
            'quotationBanner' => $banners->get('quotation', collect())->first(),
            // All top-level categories, shown as a horizontally scrollable card row.
            'shortcutCategories' => $shortcut = CategoryVisibility::filter(Category::active()->whereNull('parent_id')->orderBy('sort_order')->get()),
            'categoryThumbs' => CategoryVisibility::thumbnails($shortcut),
            // Produk terlaris berdasarkan jumlah unit yang benar-benar terjual
            // (sold_count naik saat pesanan dibayar, termasuk penjualan manual
            // Tokopedia/offline). Sebelum ada riwayat penjualan, bagian ini
            // memakai produk unggulan pilihan admin agar beranda tidak kosong.
            'bestSellers' => Product::published()->where('sold_count', '>', 0)
                ->with(Product::CARD_RELATIONS)->orderByDesc('sold_count')->latest('published_at')->take(10)->get(),
            'featured' => Product::published()->where('is_featured', true)->with(Product::CARD_RELATIONS)->latest('published_at')->take(10)->get(),
            'newest' => Product::published()->where('is_new', true)->with(Product::CARD_RELATIONS)->latest('published_at')->take(10)->get(),
            'promos' => Product::published()->whereNotNull('sale_price')->where('is_clearance', false)->with(Product::CARD_RELATIONS)->take(10)->get(),
            'clearance' => Product::published()
                ->where(fn ($q) => $q->where('is_clearance', true)->orWhere('condition', '!=', 'new'))
                ->with(Product::CARD_RELATIONS)
                ->latest('published_at')
                ->take(12)
                ->get(),
            'brands' => Brand::active()->where('is_featured', true)->orderBy('sort_order')->take(12)->get(),
            'articles' => Article::published()->latest('published_at')->take(3)->get(),
            'testimonials' => Review::visible()->where('rating', '>=', 4)->whereNotNull('comment')->with(['user', 'product'])->latest()->take(6)->get(),
        ]);
    }
}
