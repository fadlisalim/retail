<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Product;
use App\Services\ComparisonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompareController extends Controller
{
    public function __construct(private readonly ComparisonService $comparison)
    {
    }

    public function index(): View
    {
        $products = $this->comparison->items();

        // Union of attributes across the compared products, to build the table rows.
        $attributeIds = $products->flatMap(fn ($p) => $p->attributeValues->pluck('attribute_id'))->unique();
        $attributes = Attribute::whereIn('id', $attributeIds)->orderBy('sort_order')->get();

        return view('storefront.compare', [
            'products' => $products,
            'attributes' => $attributes,
        ]);
    }

    public function add(Product $product): RedirectResponse
    {
        $this->comparison->add($product);

        return back()->with('success', 'Produk ditambahkan ke perbandingan.');
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->comparison->remove($product);

        return back()->with('success', 'Produk dihapus dari perbandingan.');
    }

    public function clear(): RedirectResponse
    {
        $this->comparison->clear();

        return back()->with('success', 'Perbandingan dikosongkan.');
    }
}
