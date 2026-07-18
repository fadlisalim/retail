<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(private readonly SearchService $search)
    {
    }

    public function index(Request $request): View
    {
        return $this->render($request, [
            'title' => 'Semua Produk',
            'breadcrumbs' => [['label' => 'Produk']],
        ]);
    }

    /** Directory of all active categories with their sub-categories. */
    public function categories(): View
    {
        $categories = Category::active()
            ->whereNull('parent_id')
            ->with(['activeChildren' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('storefront.categories', ['categories' => $categories]);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        $breadcrumbs = array_map(
            fn (Category $c) => ['label' => $c->name, 'url' => route('categories.show', $c->slug)],
            $category->ancestors(),
        );

        return $this->render($request->merge(['category' => $category->slug]), [
            'title' => $category->meta_title ?: $category->name,
            'metaDescription' => $category->meta_description,
            'category' => $category,
            'heading' => $category->name,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /** Directory of all active brands (with published-product counts). */
    public function brands(): View
    {
        $brands = Brand::active()
            ->withCount(['products' => fn ($q) => $q->published()])
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return view('storefront.brands', ['brands' => $brands]);
    }

    public function brand(Request $request, Brand $brand): View
    {
        abort_unless($brand->is_active, 404);

        // Banners for this brand's page: brand-specific first, then any global
        // brand banner (brand_id null). Rendered as a slider above the grid.
        $brandBanners = Banner::active()
            ->where('position', 'brand')
            ->where(fn ($q) => $q->where('brand_id', $brand->id)->orWhereNull('brand_id'))
            ->orderBy('sort_order')
            ->get();

        return $this->render($request->merge(['brand' => $brand->slug]), [
            'title' => $brand->meta_title ?: 'Produk '.$brand->name,
            'metaDescription' => $brand->meta_description,
            'heading' => 'Brand: '.$brand->name,
            'breadcrumbs' => [['label' => 'Brand'], ['label' => $brand->name]],
            'brandBanners' => $brandBanners,
        ]);
    }

    public function promo(Request $request): View
    {
        return $this->render($request->merge(['promo' => 1]), [
            'title' => 'Promo & Penawaran Spesial',
            'heading' => 'Promo',
            'breadcrumbs' => [['label' => 'Promo']],
        ]);
    }

    public function newest(Request $request): View
    {
        return $this->render($request->merge(['new' => 1, 'sort' => $request->get('sort', 'newest')]), [
            'title' => 'Produk Baru',
            'heading' => 'Produk Baru',
            'breadcrumbs' => [['label' => 'Produk Baru']],
        ]);
    }

    public function clearance(Request $request): View
    {
        return $this->render($request->merge(['clearance' => 1]), [
            'title' => 'Barang Clearance',
            'heading' => 'Clearance',
            'breadcrumbs' => [['label' => 'Clearance']],
        ]);
    }

    /** Shared catalog renderer: runs the search, builds filter facets, returns the grid view. */
    private function render(Request $request, array $view): View
    {
        $filters = $request->only([
            'q', 'category', 'brand', 'price_min', 'price_max', 'condition',
            'in_stock', 'ready', 'quotation', 'promo', 'new', 'clearance', 'featured',
            'rating_min', 'sort', 'attr',
        ]);

        $products = $this->search->search($filters, (int) config('rekasurya.catalog.per_page', 24));

        return view('storefront.catalog', array_merge([
            'products' => $products,
            'filters' => $filters,
            'brands' => Brand::active()->orderBy('name')->get(),
            'rootCategories' => Category::active()->roots()->with('activeChildren')->orderBy('sort_order')->get(),
            'sortOptions' => $this->sortOptions(),
            'category' => null,
            'heading' => $view['title'] ?? 'Produk',
            'metaDescription' => null,
            'breadcrumbs' => [],
            'brandBanners' => collect(),
        ], $view));
    }

    private function sortOptions(): array
    {
        return [
            'relevance' => 'Paling Relevan',
            'newest' => 'Terbaru',
            'price_asc' => 'Harga Terendah',
            'price_desc' => 'Harga Tertinggi',
            'rating' => 'Rating Tertinggi',
            'best_selling' => 'Paling Laris',
            'most_viewed' => 'Paling Dilihat',
            'discount' => 'Diskon Terbesar',
        ];
    }
}
