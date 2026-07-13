<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boots the main public pages against the full demo dataset to catch view/route
 * regressions (missing variables, broken Blade, etc.).
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/produk')->assertOk();
        $this->get('/promo')->assertOk();
        $this->get('/barang-sisa-proyek')->assertOk();
        $this->get('/pencarian?q=inverter')->assertOk();
        $this->get('/permintaan-penawaran')->assertOk();
        $this->get('/faq')->assertOk();
        $this->get('/keranjang')->assertOk();
        $this->get('/masuk')->assertOk();
        $this->get('/daftar')->assertOk();
    }

    public function test_product_and_category_pages_render(): void
    {
        $product = Product::published()->first();
        $this->get('/produk/'.$product->slug)->assertOk()->assertSee($product->name);

        $this->get('/kategori/panel-surya')->assertOk();
        $this->get('/brand/bezvolt')->assertOk();
    }

    public function test_seo_endpoints_respond(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->get('/robots.txt')->assertOk();
    }

    public function test_autocomplete_returns_json(): void
    {
        $this->getJson('/api/pencarian/suggest?q=panel')->assertOk();
    }
}
