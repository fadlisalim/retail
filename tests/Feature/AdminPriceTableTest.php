<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman Harga & Margin: inline edit modal/jual/coret/fee + margin. */
class AdminPriceTableTest extends TestCase
{
    use RefreshDatabase;

    private function katalog(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());

        return $user;
    }

    public function test_the_page_lists_products_with_their_margin(): void
    {
        $this->stockedProduct(0, ['name' => 'Inverter Margin Sehat', 'price' => 10_000_000, 'cost_price' => 7_000_000]);

        $this->actingAs($this->katalog())
            ->get(route('admin.prices.index'))
            ->assertOk()
            ->assertSee('Harga & Margin')
            ->assertSee('Inverter Margin Sehat');
    }

    public function test_inline_update_saves_all_four_fields_and_returns_the_margin(): void
    {
        $product = $this->stockedProduct(0, ['price' => 5_000_000, 'cost_price' => null, 'affiliate_rate' => null]);

        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), [
                'cost_price' => 8_000_000,
                'price' => 10_000_000,
                'compare_price' => 12_500_000,
                'affiliate_rate' => 3.5,
            ])
            ->assertOk()
            ->assertJson([
                'jual' => 10_000_000,
                'coret' => 12_500_000,
                'modal' => 8_000_000,
                'fee' => 3.5,
                'diskon_pct' => 20.0,
                'margin_pct' => 20.0,
            ]);

        $product->refresh();
        // Pemetaan sama dengan form produk: coret lebih tinggi → price + sale_price.
        $this->assertEquals(12_500_000, (float) $product->price);
        $this->assertEquals(10_000_000, (float) $product->sale_price);
        $this->assertEquals(8_000_000, (float) $product->cost_price);
    }

    public function test_clearing_the_compare_price_removes_the_discount(): void
    {
        $product = $this->stockedProduct(0, ['price' => 12_500_000, 'sale_price' => 10_000_000]);

        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), ['price' => 10_000_000, 'compare_price' => null])
            ->assertOk()
            ->assertJson(['jual' => 10_000_000, 'coret' => null, 'diskon_pct' => null]);

        $this->assertNull($product->fresh()->sale_price);
    }

    public function test_variable_products_cannot_change_price_here_but_cost_and_fee_can(): void
    {
        $product = $this->stockedProduct(0, ['product_type' => 'variable', 'price' => 5_000_000]);

        // price ikut terkirim → ditolak (harga varian diatur per varian).
        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), ['price' => 9_999_999, 'cost_price' => 4_000_000])
            ->assertUnprocessable();

        // Tanpa price → modal & fee tersimpan.
        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), ['cost_price' => 4_000_000, 'affiliate_rate' => 2])
            ->assertOk();

        $product->refresh();
        $this->assertEquals(4_000_000, (float) $product->cost_price);
        $this->assertEquals(5_000_000, (float) $product->price);
    }

    public function test_the_thin_margin_filter_finds_only_thin_margins(): void
    {
        $this->stockedProduct(0, ['name' => 'Produk Margin Tipis', 'price' => 10_000_000, 'cost_price' => 9_500_000]);
        $this->stockedProduct(0, ['name' => 'Produk Margin Sehat', 'price' => 10_000_000, 'cost_price' => 7_000_000]);
        $this->stockedProduct(0, ['name' => 'Produk Tanpa Modal', 'price' => 5_000_000, 'cost_price' => null]);

        $this->actingAs($this->katalog())
            ->get(route('admin.prices.index', ['tampil' => 'margin-tipis']))
            ->assertOk()
            ->assertSee('Produk Margin Tipis')
            ->assertDontSee('Produk Margin Sehat')
            ->assertDontSee('Produk Tanpa Modal');

        $this->actingAs($this->katalog())
            ->get(route('admin.prices.index', ['tampil' => 'tanpa-modal']))
            ->assertOk()
            ->assertSee('Produk Tanpa Modal')
            ->assertDontSee('Produk Margin Tipis');
    }

    public function test_the_page_is_gated_by_the_price_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $sales = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $sales->roles()->attach(Role::where('slug', 'admin-sales')->first());
        $product = $this->stockedProduct(0, ['price' => 1_000_000]);

        $this->actingAs($sales)->get(route('admin.prices.index'))->assertForbidden();
        $this->actingAs($sales)->patchJson(route('admin.prices.update', $product), ['price' => 1])->assertForbidden();

        $this->assertEquals(1_000_000, (float) $product->fresh()->price);
    }
}
