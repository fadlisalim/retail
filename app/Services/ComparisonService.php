<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductComparison;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Product comparison, capped at 4 items and restricted to a single compatible
 * category so the spec table lines up (section 13).
 */
class ComparisonService
{
    private const MAX = 4;

    public function add(Product $product): void
    {
        $items = $this->items();

        if ($items->contains('id', $product->id)) {
            return;
        }

        if ($items->count() >= self::MAX) {
            throw ValidationException::withMessages([
                'compare' => 'Maksimal '.self::MAX.' produk dapat dibandingkan sekaligus.',
            ]);
        }

        // Only allow comparing products from the same category for a meaningful table.
        if ($items->isNotEmpty() && $items->first()->category_id !== $product->category_id) {
            throw ValidationException::withMessages([
                'compare' => 'Hanya produk dari kategori yang sama dapat dibandingkan.',
            ]);
        }

        ProductComparison::create($this->scope() + ['product_id' => $product->id]);
    }

    public function remove(Product $product): void
    {
        ProductComparison::where($this->scope())->where('product_id', $product->id)->delete();
    }

    public function clear(): void
    {
        ProductComparison::where($this->scope())->delete();
    }

    /** @return Collection<int,Product> */
    public function items(): Collection
    {
        $ids = ProductComparison::where($this->scope())->pluck('product_id');

        return Product::with(['brand', 'category', 'attributeValues.attribute'])
            ->whereIn('id', $ids)->get();
    }

    public function count(): int
    {
        return ProductComparison::where($this->scope())->count();
    }

    private function scope(): array
    {
        return auth()->id()
            ? ['user_id' => auth()->id()]
            : ['session_token' => session('guest_token')];
    }
}
