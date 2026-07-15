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
