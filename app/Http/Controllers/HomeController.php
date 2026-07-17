<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredCard = fn ($q) => $q->published()->with(['brand', 'category']);

        return view('storefront.home', [
            'heroBanners' => Banner::active()->where('position', 'hero')->orderBy('sort_order')->get(),
            'gridBanners' => Banner::active()->where('position', 'grid')->orderBy('sort_order')->get(),
            'videoBanners' => Banner::active()->where('position', 'video')->orderBy('sort_order')->get(),
            'quotationBanner' => Banner::active()->where('position', 'quotation')->orderBy('sort_order')->first(),
            // All top-level categories, shown as a horizontally scrollable card row.
            'shortcutCategories' => Category::active()->whereNull('parent_id')->orderBy('sort_order')->get(),
            'featured' => Product::published()->where('is_featured', true)->with(['brand', 'category'])->latest('published_at')->take(10)->get(),
            'newest' => Product::published()->where('is_new', true)->with(['brand', 'category'])->latest('published_at')->take(10)->get(),
            'promos' => Product::published()->whereNotNull('sale_price')->where('is_clearance', false)->with(['brand', 'category'])->take(10)->get(),
            'clearance' => Product::published()
                ->where(fn ($q) => $q->where('is_clearance', true)->orWhere('condition', '!=', 'new'))
                ->with(['brand', 'category'])
                ->latest('published_at')
                ->take(12)
                ->get(),
            'brands' => Brand::active()->where('is_featured', true)->orderBy('sort_order')->take(12)->get(),
            'mostViewed' => Product::published()->with(['brand', 'category'])->orderByDesc('view_count')->take(10)->get(),
            'topRated' => Product::published()->where('rating_count', '>', 0)->with(['brand', 'category'])->orderByDesc('rating_avg')->take(10)->get(),
            'articles' => Article::published()->latest('published_at')->take(3)->get(),
            'testimonials' => Review::visible()->where('rating', '>=', 4)->whereNotNull('comment')->with(['user', 'product'])->latest()->take(6)->get(),
        ]);
    }
}
