<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInlineBrandTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'SKU-INLINE-1',
            'name' => 'Panel Uji Inline',
            'product_type' => 'simple',
            'condition' => 'new',
            'price' => 1000000,
            'unit' => 'pcs',
            'status' => 'draft',
        ], $overrides);
    }

    public function test_new_brand_is_created_and_assigned(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.products.store'), $this->payload(['new_brand' => 'BrandBaruKu']))
            ->assertRedirect();

        $brand = Brand::where('name', 'BrandBaruKu')->first();
        $this->assertNotNull($brand);
        $this->assertSame($brand->id, Product::where('sku', 'SKU-INLINE-1')->first()->brand_id);
    }

    public function test_compare_price_maps_to_strikethrough_and_sale_price(): void
    {
        $this->actingAs($this->staff());

        // Harga Jual 39jt, Harga Coret 45jt → price=45jt (struck), sale_price=39jt (charged).
        $this->post(route('admin.products.store'), $this->payload([
            'sku' => 'SKU-PRICE-1', 'price' => 39000000, 'compare_price' => 45000000,
        ]))->assertRedirect();

        $p = Product::where('sku', 'SKU-PRICE-1')->first();
        $this->assertEquals(45000000, (float) $p->price);
        $this->assertEquals(39000000, (float) $p->sale_price);
        $this->assertTrue($p->isOnSale());
        $this->assertEquals(39000000, $p->effectivePrice());
    }

    public function test_no_compare_price_leaves_sale_price_null(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.products.store'), $this->payload([
            'sku' => 'SKU-PRICE-2', 'price' => 10000000,
        ]))->assertRedirect();

        $p = Product::where('sku', 'SKU-PRICE-2')->first();
        $this->assertEquals(10000000, (float) $p->price);
        $this->assertNull($p->sale_price);
    }

    public function test_specifications_built_from_rows(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.products.store'), $this->payload([
            'sku' => 'SKU-SPEC-1',
            'spec_key' => ['Daya Output', 'Tegangan', ''],       // last row blank → skipped
            'spec_value' => ['6000 W', '51.2 V', ''],
        ]))->assertRedirect();

        $p = Product::where('sku', 'SKU-SPEC-1')->first();
        $this->assertStringContainsString('<th>Daya Output</th><td>6000 W</td>', $p->specifications);
        $this->assertStringContainsString('<th>Tegangan</th><td>51.2 V</td>', $p->specifications);
        $this->assertStringNotContainsString('<th></th>', $p->specifications);
    }

    public function test_products_are_always_purchasable_never_rfq(): void
    {
        $this->actingAs($this->staff());

        // Even if the (now-removed) toggles are posted, policy wins.
        $this->post(route('admin.products.store'), $this->payload([
            'sku' => 'SKU-POLICY-1', 'is_purchasable' => '0', 'requires_quotation' => '1',
        ]))->assertRedirect();

        $p = Product::where('sku', 'SKU-POLICY-1')->first();
        $this->assertTrue((bool) $p->is_purchasable);
        $this->assertFalse((bool) $p->requires_quotation);
        $this->assertEquals(1, $p->min_purchase);
    }

    public function test_sku_is_auto_generated_when_blank(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.products.store'), $this->payload([
            'sku' => '', 'name' => 'Inverter Hybrid 6kW',
        ]))->assertRedirect();

        $p = Product::where('name', 'Inverter Hybrid 6kW')->first();
        $this->assertNotEmpty($p->sku);
    }

    public function test_custom_badge_text_is_saved_and_shown_in_badges(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.products.store'), $this->payload([
            'sku' => 'SKU-BADGE-1', 'badge_text' => 'JAMINAN HARGA TERMURAH',
        ]))->assertRedirect();

        $p = Product::where('sku', 'SKU-BADGE-1')->first();
        $this->assertSame('JAMINAN HARGA TERMURAH', $p->badge_text);
        $this->assertContains('JAMINAN HARGA TERMURAH', $p->badges());
    }

    public function test_blank_badge_text_is_stored_as_null(): void
    {
        $this->actingAs($this->staff());

        $this->post(route('admin.products.store'), $this->payload([
            'sku' => 'SKU-BADGE-2', 'badge_text' => '   ',
        ]))->assertRedirect();

        $this->assertNull(Product::where('sku', 'SKU-BADGE-2')->first()->badge_text);
    }

    public function test_index_shows_fast_edit_control(): void
    {
        $this->actingAs($this->staff());
        $this->post(route('admin.products.store'), $this->payload(['sku' => 'SKU-FE-0']))->assertRedirect();

        $this->get(route('admin.products.index'))->assertOk()->assertSee('Edit cepat');
    }

    public function test_index_shows_affiliate_commission_column(): void
    {
        $this->actingAs($this->staff());
        $withRate = $this->stockedProduct(0, ['name' => 'ProdukKomisi', 'affiliate_rate' => 7.5]);

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Komisi')      // column header
            ->assertSee('7,5%');       // the product's own rate
    }

    public function test_bulk_status_publishes_selected_products_only(): void
    {
        $this->actingAs($this->staff());
        $a = $this->stockedProduct(0, ['status' => 'draft']);
        $b = $this->stockedProduct(0, ['status' => 'draft']);
        $untouched = $this->stockedProduct(0, ['status' => 'draft']);

        $this->post(route('admin.products.bulk-status'), [
            'ids' => [$a->id, $b->id],
            'status' => 'published',
        ])->assertRedirect();

        $this->assertSame('published', $a->fresh()->status);
        $this->assertNotNull($a->fresh()->published_at);
        $this->assertSame('published', $b->fresh()->status);
        $this->assertSame('draft', $untouched->fresh()->status);
    }

    /** Menerbitkan ulang tidak boleh menggeser tanggal terbit yang sudah ada. */
    public function test_bulk_status_keeps_the_original_publish_date(): void
    {
        $this->actingAs($this->staff());
        $published = $this->stockedProduct(0, ['status' => 'published', 'published_at' => now()->subMonth()]);
        $original = $published->published_at;

        $this->post(route('admin.products.bulk-status'), ['ids' => [$published->id], 'status' => 'draft']);
        $this->post(route('admin.products.bulk-status'), ['ids' => [$published->id], 'status' => 'published']);

        $this->assertTrue($original->equalTo($published->fresh()->published_at));
    }

    public function test_bulk_status_rejects_an_unknown_status(): void
    {
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(0, ['status' => 'draft']);

        $this->post(route('admin.products.bulk-status'), [
            'ids' => [$product->id],
            'status' => 'terbit-banget',
        ])->assertSessionHasErrors('status');

        $this->assertSame('draft', $product->fresh()->status);
    }

    public function test_bulk_status_is_closed_to_staff_without_catalog_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $sales = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $sales->roles()->attach(Role::where('slug', 'admin-sales')->first());
        $product = $this->stockedProduct(0, ['status' => 'draft']);

        $this->actingAs($sales)
            ->post(route('admin.products.bulk-status'), ['ids' => [$product->id], 'status' => 'published'])
            ->assertForbidden();

        $this->assertSame('draft', $product->fresh()->status);
    }

    public function test_index_shows_the_bulk_select_column(): void
    {
        $this->actingAs($this->staff());
        $this->stockedProduct(0, ['status' => 'draft']);

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('bulk-status-form')
            ->assertSee('produk dipilih');
    }

    public function test_fast_edit_updates_price_commission_status_and_stock(): void
    {
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(5, ['product_type' => 'simple', 'price' => 1000000, 'status' => 'draft']);

        $this->put(route('admin.products.quick', $product), [
            'price' => 1900000,
            'compare_price' => 2700000,   // coret > jual → struck price
            'affiliate_rate' => 7.5,
            'status' => 'published',
            'stock' => 12,
        ])->assertRedirect();

        $product->refresh();
        $this->assertEquals(2700000, (float) $product->price);
        $this->assertEquals(1900000, (float) $product->sale_price);
        $this->assertEquals(7.5, (float) $product->affiliate_rate);
        $this->assertSame('published', $product->status);
        $this->assertNotNull($product->published_at);
        $this->assertEquals(12, $product->fresh()->stock);
    }

    public function test_fast_edit_ignores_stock_for_variable_products(): void
    {
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(0, ['product_type' => 'variable', 'price' => 500000]);
        $before = $product->stock;

        $this->put(route('admin.products.quick', $product), [
            'price' => 500000, 'status' => 'published', 'stock' => 99,
        ])->assertRedirect();

        // Variable stock is managed per variant — the posted stock is ignored.
        $this->assertEquals($before, $product->fresh()->stock);
    }

    public function test_existing_brand_is_reused_not_duplicated(): void
    {
        $this->actingAs($this->staff());
        $existing = Brand::create(['name' => 'Growatt', 'slug' => 'growatt', 'is_active' => true]);

        $this->post(route('admin.products.store'), $this->payload(['sku' => 'SKU-INLINE-2', 'new_brand' => 'Growatt']))
            ->assertRedirect();

        $this->assertSame(1, Brand::where('slug', 'growatt')->count());
        $this->assertSame($existing->id, Product::where('sku', 'SKU-INLINE-2')->first()->brand_id);
    }
}
