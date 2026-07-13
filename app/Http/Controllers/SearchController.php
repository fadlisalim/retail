<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only([
            'q', 'category', 'brand', 'price_min', 'price_max', 'condition',
            'in_stock', 'ready', 'quotation', 'promo', 'new', 'clearance',
            'rating_min', 'sort', 'attr',
        ]);

        $products = $this->search->search($filters, (int) config('rekasurya.catalog.per_page', 24));

        return view('storefront.catalog', [
            'products' => $products,
            'filters' => $filters,
            'brands' => Brand::active()->orderBy('name')->get(),
            'rootCategories' => Category::active()->roots()->with('activeChildren')->orderBy('sort_order')->get(),
            'sortOptions' => [
                'relevance' => 'Paling Relevan', 'newest' => 'Terbaru',
                'price_asc' => 'Harga Terendah', 'price_desc' => 'Harga Tertinggi',
                'rating' => 'Rating Tertinggi', 'best_selling' => 'Paling Laris',
                'most_viewed' => 'Paling Dilihat', 'discount' => 'Diskon Terbesar',
            ],
            'category' => null,
            'heading' => $request->filled('q') ? 'Hasil pencarian: "'.e($request->get('q')).'"' : 'Pencarian',
            'title' => $request->filled('q') ? 'Cari: '.$request->get('q') : 'Pencarian Produk',
            'metaDescription' => null,
            'breadcrumbs' => [['label' => 'Pencarian']],
            'noindex' => true,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json($this->search->suggest((string) $request->get('q')));
    }
}
