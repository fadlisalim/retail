<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Jumlah query halaman toko tidak boleh tumbuh per produk/kategori (N+1). */
class StorefrontQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function queriesFor(string $uri): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $this->get($uri)->assertOk();

        return $count;
    }

    public function test_home_query_count_does_not_grow_with_number_of_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id, 'is_featured' => true, 'is_new' => true]);
        $this->get('/'); // panaskan cache menu dulu
        $few = $this->queriesFor('/');

        Product::factory()->count(9)->create(['category_id' => $category->id, 'is_featured' => true, 'is_new' => true]);
        $this->get('/');
        $many = $this->queriesFor('/');

        $this->assertSame($few, $many, "Beranda: {$few} query (3 produk) vs {$many} query (12 produk)");
    }

    public function test_descendant_ids_cover_all_levels_in_one_query(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);
        $other = Category::factory()->create();

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $ids = $root->descendantIds();

        $this->assertEqualsCanonicalizing([$root->id, $child->id, $grandchild->id], $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertSame(1, $count);
    }
}
