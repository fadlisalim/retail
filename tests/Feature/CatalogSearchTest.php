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

    public function test_category_page_shows_banner_and_description_as_landing(): void
    {
        $cat = Category::factory()->create(['slug' => 'panel-surya-x', 'description' => 'Penjelasan kategori panel surya untuk landing.']);
        $other = Category::factory()->create(['slug' => 'inverter-x']);
        \App\Models\Banner::create([
            'title' => 'Banner Kategori Panel', 'position' => 'category', 'category_id' => $cat->id,
            'image_desktop_path' => 'banners/cat-demo.webp', 'is_active' => true, 'sort_order' => 0,
        ]);

        // Target category: banner slider + intro shown.
        $this->get('/kategori/panel-surya-x')
            ->assertOk()
            ->assertSee('banners/cat-demo.webp')
            ->assertSee('Penjelasan kategori panel surya untuk landing.');

        // Other category: neither leaks.
        $this->get('/kategori/inverter-x')
            ->assertOk()
            ->assertDontSee('banners/cat-demo.webp')
            ->assertDontSee('Penjelasan kategori panel surya untuk landing.');
    }

    public function test_category_filter_scopes_results(): void
    {
        $panels = Category::factory()->create(['slug' => 'panel-surya']);
        $inverters = Category::factory()->create(['slug' => 'inverter']);
        Product::factory()->create(['name' => 'PanelX', 'category_id' => $panels->id, 'status' => 'published']);
        Product::factory()->create(['name' => 'InverterY', 'category_id' => $inverters->id, 'status' => 'published']);

        $this->get('/kategori/panel-surya')->assertOk()->assertSee('PanelX')->assertDontSee('InverterY');
    }

    public function test_category_browsing_shows_clearance_items_too(): void
    {
        $cat = Category::factory()->create(['slug' => 'panel-surya']);
        Product::factory()->create(['name' => 'PanelBaru', 'category_id' => $cat->id, 'status' => 'published', 'is_clearance' => false]);
        Product::factory()->create(['name' => 'PanelClearance', 'category_id' => $cat->id, 'status' => 'published', 'is_clearance' => true]);

        // Clearance items appear in their category too (and also on the clearance page).
        $this->get('/kategori/panel-surya')
            ->assertOk()
            ->assertSee('PanelBaru')
            ->assertSee('PanelClearance');

        $this->get('/barang-clearance')->assertOk()->assertSee('PanelClearance');
    }

    public function test_active_filter_chips_render(): void
    {
        Category::factory()->create(['slug' => 'panel-surya']);
        Product::factory()->create(['name' => 'PanelX', 'status' => 'published']);

        $this->get('/produk?condition[]=new_minor_defect&price_min=1000000')
            ->assertOk()
            ->assertSee('Filter aktif')
            ->assertSee('Baru - Minor Defect')
            ->assertSee('Hapus semua');
    }
}
