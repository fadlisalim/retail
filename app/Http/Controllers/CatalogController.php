<?php

namespace App\Http\Controllers;

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

    public function brand(Request $request, Brand $brand): View
    {
        abort_unless($brand->is_active, 404);

        return $this->render($request->merge(['brand' => $brand->slug]), [
            'title' => $brand->meta_title ?: 'Produk '.$brand->name,
            'metaDescription' => $brand->meta_description,
            'heading' => 'Brand: '.$brand->name,
            'breadcrumbs' => [['label' => 'Brand'], ['label' => $brand->name]],
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

    public function surplus(Request $request): View
    {
        // Project surplus = clearance or non-new condition items.
        $category = Category::where('slug', 'barang-sisa-proyek')->first();

        return $this->render($request->merge($category ? ['category' => $category->slug] : ['clearance' => 1]), [
            'title' => 'Barang Sisa Proyek',
            'heading' => 'Barang Sisa Proyek',
            'breadcrumbs' => [['label' => 'Barang Sisa Proyek']],
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
