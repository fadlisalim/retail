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
