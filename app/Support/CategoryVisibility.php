<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menyembunyikan kategori kosong dari navigasi toko (mega menu, beranda, direktori
 * kategori) supaya pengunjung tidak mengklik menu yang hasilnya "0 produk".
 * Kategori dianggap terisi bila punya produk terbit (kategori utama maupun
 * kategori tambahan) — langsung atau lewat salah satu turunannya.
 */
class CategoryVisibility
{
    /** @return list<int> */
    public static function populatedIds(): array
    {
        return Cache::remember('categories:populated', 600, function () {
            $direct = DB::table('products')->where('status', 'published')->whereNull('deleted_at')->whereNotNull('category_id')->distinct()->pluck('category_id');
            $pivot = DB::table('category_product')
                ->join('products', 'products.id', '=', 'category_product.product_id')
                ->where('products.status', 'published')->whereNull('products.deleted_at')
                ->distinct()->pluck('category_product.category_id');

            $ids = $direct->merge($pivot)->map(fn ($id) => (int) $id)->unique();
            $parents = Category::pluck('parent_id', 'id');
            foreach ($ids->all() as $id) {
                $guard = 0;
                while (($parent = $parents[$id] ?? null) && $guard++ < 10) {
                    $ids->push((int) $parent);
                    $id = $parent;
                }
            }

            return $ids->unique()->values()->all();
        });
    }

    /**
     * Saring koleksi kategori akar (dengan relasi activeChildren) → hanya yang terisi.
     *
     * @param  Collection<int, Category>  $roots
     * @return Collection<int, Category>
     */
    public static function filter(Collection $roots): Collection
    {
        $ids = array_flip(self::populatedIds());

        return $roots->filter(fn (Category $c) => isset($ids[$c->id]))
            ->each(function (Category $c) use ($ids) {
                if ($c->relationLoaded('activeChildren')) {
                    $c->setRelation('activeChildren', $c->activeChildren->filter(fn (Category $child) => isset($ids[$child->id]))->values());
                }
            })
            ->values();
    }
}
