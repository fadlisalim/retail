<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Catalog search + faceted filtering. Uses MySQL FULLTEXT when available and a
 * safe parameterised LIKE fallback otherwise (e.g. SQLite in tests). All filter
 * state is read from the query string so any result page is shareable (spec §6).
 */
class SearchService
{
    public function search(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = Product::query()
            ->published()
            ->with(['brand', 'category']);

        $this->applyKeyword($query, $filters['q'] ?? null);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? null, ! empty($filters['q']));

        return $query->paginate($perPage)->withQueryString();
    }

    private function applyKeyword(Builder $query, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $query->whereFullText(['name', 'short_description', 'description', 'keywords'], $keyword)
                ->orWhere('sku', 'like', "%{$keyword}%")
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$keyword}%"));

            return;
        }

        // Portable fallback (SQLite/Postgres): parameterised LIKE across fields.
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $keyword).'%';
        $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('model', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('keywords', 'like', $like)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like));
        });
    }

    private function applyFilters(Builder $query, array $f): void
    {
        if (! empty($f['category'])) {
            $category = $f['category'] instanceof Category
                ? $f['category']
                : Category::where('slug', $f['category'])->orWhere('id', $f['category'])->first();
            if ($category) {
                $query->whereIn('category_id', $category->descendantIds());
            }
        }

        if (! empty($f['brand'])) {
            $brands = (array) $f['brand'];
            $query->whereHas('brand', fn ($b) => $b->whereIn('slug', $brands)->orWhereIn('id', $brands));
        }

        // Effective price uses the sale price when present.
        if (isset($f['price_min']) && $f['price_min'] !== '') {
            $query->whereRaw('COALESCE(sale_price, price) >= ?', [(float) $f['price_min']]);
        }
        if (isset($f['price_max']) && $f['price_max'] !== '') {
            $query->whereRaw('COALESCE(sale_price, price) <= ?', [(float) $f['price_max']]);
        }

        if (! empty($f['condition'])) {
            $query->whereIn('condition', (array) $f['condition']);
        }

        if (! empty($f['in_stock'])) {
            $query->where('stock', '>', 0);
        }
        if (! empty($f['ready'])) { // siap kirim
            $query->where('stock', '>', 0)->where('is_purchasable', true)->where('requires_quotation', false);
        }
        if (! empty($f['quotation'])) {
            $query->where('requires_quotation', true);
        }
        if (! empty($f['promo'])) {
            $query->where(fn ($q) => $q->where('is_promo', true)->orWhereNotNull('sale_price'));
        }
        if (! empty($f['new'])) {
            $query->where('is_new', true);
        }
        if (! empty($f['clearance'])) {
            $query->where('is_clearance', true);
        }
        // Keep clearance/surplus deals out of normal category browsing.
        if (! empty($f['exclude_clearance'])) {
            $query->where(fn ($q) => $q->where('is_clearance', false)->orWhereNull('is_clearance'));
        }
        if (! empty($f['featured'])) {
            $query->where('is_featured', true);
        }
        if (isset($f['rating_min']) && $f['rating_min'] !== '') {
            $query->where('rating_avg', '>=', (float) $f['rating_min']);
        }

        // Dynamic attribute filters: attr[slug] = value(s)
        if (! empty($f['attr']) && is_array($f['attr'])) {
            foreach ($f['attr'] as $slug => $values) {
                $values = array_filter((array) $values, fn ($v) => $v !== '');
                if (empty($values)) {
                    continue;
                }
                $query->whereHas('attributeValues', function ($q) use ($slug, $values) {
                    $q->whereHas('attribute', fn ($a) => $a->where('slug', $slug))
                        ->whereIn('value_text', $values);
                });
            }
        }
    }

    private function applySort(Builder $query, ?string $sort, bool $hasKeyword): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) desc'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            'best_selling' => $query->orderByDesc('sold_count'),
            'most_viewed' => $query->orderByDesc('view_count'),
            'discount' => $query->orderByRaw('CASE WHEN sale_price IS NULL THEN 0 ELSE (price - sale_price) / price END desc'),
            default => $hasKeyword
                ? $query->orderByDesc('sold_count')->orderByDesc('rating_avg')
                : $query->orderByDesc('is_featured')->orderByDesc('published_at'),
        };
    }

    /** Autocomplete suggestions for the header search box. */
    public function suggest(string $keyword, int $limit = 8): array
    {
        $keyword = trim($keyword);
        if (mb_strlen($keyword) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $keyword).'%';

        return Product::published()
            ->with('brand', 'category')
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like)))
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'slug' => $p->slug,
                'url' => route('products.show', $p->slug),
                'image' => $p->primaryImageUrl(),
                'price' => rupiah($p->effectivePrice()),
                'brand' => $p->brand?->name,
                'category' => $p->category?->name,
            ])
            ->all();
    }
}
