<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_a_shareable_unique_url(): void
    {
        $product = Product::factory()->create(['slug' => 'inverter-hybrid-6kw']);

        $this->get('/produk/inverter-hybrid-6kw')->assertOk()->assertSee($product->name);
    }

    public function test_old_slug_redirects_to_current(): void
    {
        $product = Product::factory()->create(['slug' => 'nama-baru']);
        $product->slugHistories()->create(['old_slug' => 'nama-lama']);

        $this->get('/produk/nama-lama')->assertRedirect('/produk/nama-baru');
    }

    public function test_search_finds_product_by_name(): void
    {
        Product::factory()->create(['name' => 'Panel Surya 550Wp Mono', 'status' => 'published']);
        Product::factory()->create(['name' => 'Inverter Hybrid', 'status' => 'published']);

        $this->get('/pencarian?q=Panel')->assertOk()->assertSee('Panel Surya 550Wp Mono');
    }

    public function test_category_filter_scopes_results(): void
    {
        $panels = Category::factory()->create(['slug' => 'panel-surya']);
        $inverters = Category::factory()->create(['slug' => 'inverter']);
        Product::factory()->create(['name' => 'PanelX', 'category_id' => $panels->id, 'status' => 'published']);
        Product::factory()->create(['name' => 'InverterY', 'category_id' => $inverters->id, 'status' => 'published']);

        $this->get('/kategori/panel-surya')->assertOk()->assertSee('PanelX')->assertDontSee('InverterY');
    }

    public function test_category_browsing_hides_clearance_items(): void
    {
        $cat = Category::factory()->create(['slug' => 'panel-surya']);
        Product::factory()->create(['name' => 'PanelBaru', 'category_id' => $cat->id, 'status' => 'published', 'is_clearance' => false]);
        Product::factory()->create(['name' => 'PanelClearance', 'category_id' => $cat->id, 'status' => 'published', 'is_clearance' => true]);

        // Clearance items are surfaced only on the homepage/clearance page, not category browsing.
        $this->get('/kategori/panel-surya')
            ->assertOk()
            ->assertSee('PanelBaru')
            ->assertDontSee('PanelClearance');

        // …but the dedicated clearance page still shows them.
        $this->get('/barang-clearance')->assertOk()->assertSee('PanelClearance');
    }
}
