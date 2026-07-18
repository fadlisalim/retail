<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, ?int $parentId = null): Category
    {
        return Category::create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_parent_category_includes_subcategory_products(): void
    {
        $panel = $this->category('Panel Surya');
        $mono = $this->category('Monocrystalline', $panel->id);

        $product = $this->stockedProduct(5, ['category_id' => $mono->id, 'status' => 'published', 'published_at' => now()]);
        $product->categories()->sync([$mono->id]);

        $slugs = app(SearchService::class)->search(['category' => $panel->slug], 20)->pluck('id');

        $this->assertTrue($slugs->contains($product->id), 'Sub-category product should appear under its parent.');
    }

    public function test_product_appears_in_all_assigned_categories_and_their_parents(): void
    {
        $panel = $this->category('Panel Surya');
        $mono = $this->category('Monocrystalline', $panel->id);
        $portable = $this->category('Portable Power');
        $station = $this->category('Power Station', $portable->id);

        // Primary = monocrystalline; also assigned to power station.
        $product = $this->stockedProduct(5, ['category_id' => $mono->id, 'status' => 'published', 'published_at' => now()]);
        $product->categories()->sync([$mono->id, $station->id]);

        $svc = app(SearchService::class);

        foreach ([$mono, $panel, $station, $portable] as $cat) {
            $found = $svc->search(['category' => $cat->slug], 20)->pluck('id')->contains($product->id);
            $this->assertTrue($found, "Product should appear under category {$cat->name}.");
        }
    }

    public function test_product_not_shown_in_unrelated_category(): void
    {
        $mono = $this->category('Monocrystalline');
        $inverter = $this->category('Inverter');

        $product = $this->stockedProduct(5, ['category_id' => $mono->id, 'status' => 'published', 'published_at' => now()]);
        $product->categories()->sync([$mono->id]);

        $found = app(SearchService::class)->search(['category' => $inverter->slug], 20)->pluck('id')->contains($product->id);
        $this->assertFalse($found, 'Product must not leak into an unrelated category.');
    }
}
