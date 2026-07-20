<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUniqueViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_views_count_distinct_visitors(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $url = '/produk/'.$product->slug;

        // Same visitor (same session) views twice.
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $product->refresh();
        $this->assertSame(2, (int) $product->view_count);   // every visit counts
        $this->assertSame(1, (int) $product->unique_views);  // but one visitor

        // A different visitor (fresh session) is a new unique view.
        $this->flushSession();
        $this->get($url)->assertOk();

        $product->refresh();
        $this->assertSame(3, (int) $product->view_count);
        $this->assertSame(2, (int) $product->unique_views);
    }

    public function test_admin_product_list_shows_unique_views(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        $product = Product::factory()->create(['status' => 'published']);
        $product->forceFill(['unique_views' => 7, 'view_count' => 15])->save();

        $this->actingAs($admin)->get('/admin/produk')
            ->assertOk()
            ->assertSee('Dilihat')
            ->assertSee('7')
            ->assertSee('15 total');
    }
}
