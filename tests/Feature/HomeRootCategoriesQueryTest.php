<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Beranda: kategori utama (header + deretan kategori) diambil sekali per request. */
class HomeRootCategoriesQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_loads_root_categories_once(): void
    {
        $category = Category::factory()->create(['is_active' => true, 'parent_id' => null]);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        DB::enableQueryLog();
        $response = $this->get('/');
        $rootQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'from "categories"') && str_contains($q['query'], '"parent_id" is null'));

        $response->assertOk();
        $this->assertCount(1, $rootQueries);
        $this->assertTrue($response->viewData('shortcutCategories')->contains('id', $category->id));
    }
}
