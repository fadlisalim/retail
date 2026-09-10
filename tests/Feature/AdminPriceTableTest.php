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
            ->assertSee('Edit Cepat Produk')
            ->assertSee('Inverter Margin Sehat');
    }

    public function test_weight_and_dimensions_can_be_edited_inline_with_volumetric_weight(): void
    {
        $product = $this->stockedProduct(0, ['price' => 21_300, 'weight_grams' => 250, 'length_cm' => 12, 'width_cm' => 6, 'height_cm' => 6]);

        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), [
                'price' => 21_300, 'weight_grams' => 100, 'length_cm' => 6, 'width_cm' => 5, 'height_cm' => 4,
            ])
            ->assertOk()
            ->assertJson(['berat' => 100, 'p' => 6, 'l' => 5, 't' => 4, 'volumetrik' => 20]); // 6×5×4 ÷ 6000 = 0,02 kg

        $product->refresh();
        $this->assertSame(100, (int) $product->weight_grams);
        $this->assertEquals(6, (float) $product->length_cm);
        $this->assertEquals(4, (float) $product->height_cm);

        // Sel dikosongkan → 0 (kolom produk tidak nullable), bukan error.
        $this->patchJson(route('admin.prices.update', $product), ['price' => 21_300, 'weight_grams' => null, 'length_cm' => null])
            ->assertOk()->assertJson(['berat' => 0, 'p' => 0, 'volumetrik' => 0]);
    }

    /** Berat/dimensi varian kosong = mengikuti induk; terisi = milik varian. */
    public function test_variant_weight_falls_back_to_the_parent_until_set(): void
    {
        $product = $this->stockedProduct(0, ['product_type' => 'variable', 'price' => 2_490_000, 'weight_grams' => 27_100, 'length_cm' => 238, 'width_cm' => 113, 'height_cm' => 3]);
        $variant = $product->variants()->create(['sku' => 'AIKO-640', 'name' => '640 Wp', 'option_values' => ['Daya' => '640 Wp'], 'price' => 2_490_000, 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), ['variant_id' => $variant->id, 'price' => 2_490_000])
            ->assertOk()
            ->assertJson(['berat' => null, 'berat_induk' => 27_100, 'berat_efektif' => 27_100, 'p_induk' => 238, 'volumetrik' => 13_447]); // 238×113×3 ÷ 6000 = 13,447 kg

        $this->patchJson(route('admin.prices.update', $product), ['variant_id' => $variant->id, 'price' => 2_490_000, 'weight_grams' => 30_000, 'height_cm' => 4])
            ->assertOk()
            ->assertJson(['berat' => 30_000, 'berat_efektif' => 30_000, 't' => 4, 'p' => null]);

        $variant->refresh();
        $this->assertSame(30_000, (int) $variant->weight_grams);
        $this->assertNull($variant->length_cm);
        $this->assertEquals(4, (float) $variant->height_cm);
    }

    /** Admin gudang (inventory.manage): boleh stok, berat & dimensi; kolom harga ditolak. */
    public function test_warehouse_staff_can_edit_stock_and_weight_but_not_prices(): void
    {
        $this->seed(RoleSeeder::class);
        $gudang = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $gudang->roles()->attach(Role::where('slug', 'admin-gudang')->first());
        $product = $this->stockedProduct(10, ['name' => 'Rail Gudang', 'price' => 204_000, 'weight_grams' => 2_800]);

        $this->actingAs($gudang)->get(route('admin.prices.index'))
            ->assertOk()->assertSee('Rail Gudang')->assertSee('hanya memiliki izin stok');

        $this->actingAs($gudang)
            ->patchJson(route('admin.prices.update', $product), ['stock' => 4, 'weight_grams' => 2_900])
            ->assertOk()->assertJson(['stok' => 4, 'berat' => 2_900]);

        $this->actingAs($gudang)
            ->patchJson(route('admin.prices.update', $product), ['price' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->actingAs($gudang)
            ->patchJson(route('admin.prices.update', $product), ['cost_price' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('cost_price');

        $this->assertEquals(204_000, (float) $product->fresh()->price);
    }

    public function test_stock_and_shipping_filters_find_the_right_products(): void
    {
        $this->stockedProduct(0, ['name' => 'Produk Habis', 'price' => 100_000, 'stock' => 0]);
        $this->stockedProduct(2, ['name' => 'Produk Menipis', 'price' => 100_000, 'min_stock' => 5]);
        $this->stockedProduct(50, ['name' => 'Produk Aman', 'price' => 100_000, 'min_stock' => 5, 'length_cm' => 0]);

        $this->actingAs($this->katalog())
            ->get(route('admin.prices.index', ['tampil' => 'stok-habis']))
            ->assertOk()->assertSee('Produk Habis')->assertDontSee('Produk Menipis')->assertDontSee('Produk Aman');

        $this->actingAs($this->katalog())
            ->get(route('admin.prices.index', ['tampil' => 'stok-menipis']))
            ->assertOk()->assertSee('Produk Menipis')->assertDontSee('Produk Habis')->assertDontSee('Produk Aman');

        $this->actingAs($this->katalog())
            ->get(route('admin.prices.index', ['tampil' => 'tanpa-berat']))
            ->assertOk()->assertSee('Produk Aman');
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

    /** Baris varian: harga jual/coret & stok bisa disunting dari halaman ini. */
    public function test_variant_rows_can_update_price_and_stock(): void
    {
        $this->defaultWarehouse();
        $product = $this->stockedProduct(0, ['name' => 'MCB Bervarian', 'product_type' => 'variable', 'price' => 215_000]);
        $v10 = $product->variants()->create(['sku' => 'SL7N-10A', 'name' => '10A', 'option_values' => ['Arus' => '10A'], 'price' => 215_000, 'is_active' => true, 'sort_order' => 0]);
        $v63 = $product->variants()->create(['sku' => 'SL7N-63A', 'name' => '63A', 'option_values' => ['Arus' => '63A'], 'price' => 215_000, 'is_active' => true, 'sort_order' => 1]);

        // Halaman menampilkan baris variannya.
        $this->actingAs($this->katalog())
            ->get(route('admin.prices.index'))
            ->assertOk()->assertSee('10A')->assertSee('63A');

        // Ubah harga varian 63A + isi stok 25 → tersimpan, stok lewat gudang.
        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), [
                'variant_id' => $v63->id, 'price' => 250_000, 'compare_price' => 300_000, 'stock' => 25,
            ])
            ->assertOk()
            ->assertJson(['jual' => 250_000, 'coret' => 300_000, 'stok' => 25]);

        $v63->refresh();
        $this->assertEquals(300_000, (float) $v63->price);
        $this->assertEquals(250_000, (float) $v63->sale_price);
        $this->assertSame(25, (int) $v63->stock);
        $this->assertDatabaseHas('stock_movements', ['product_variant_id' => $v63->id, 'quantity' => 25]);

        // Harga "mulai dari" produk mengikuti varian termurah.
        $this->assertEquals(215_000, (float) $product->fresh()->price);
        $this->patchJson(route('admin.prices.update', $product), ['variant_id' => $v10->id, 'price' => 199_000]);
        $this->assertEquals(199_000, (float) $product->fresh()->price);
    }

    /** Modal per varian: margin varian dari modalnya sendiri, fallback modal induk. */
    public function test_variant_cost_drives_its_margin_with_parent_fallback(): void
    {
        $product = $this->stockedProduct(0, ['product_type' => 'variable', 'price' => 18_000, 'cost_price' => 14_000]);
        $v4 = $product->variants()->create(['sku' => 'PV-4', 'name' => '4mm²', 'option_values' => ['P' => '4'], 'price' => 18_000, 'is_active' => true, 'sort_order' => 0]);
        $v6 = $product->variants()->create(['sku' => 'PV-6', 'name' => '6mm²', 'option_values' => ['P' => '6'], 'price' => 24_000, 'is_active' => true, 'sort_order' => 1]);

        // Tanpa modal varian → margin memakai modal induk 14.000: (24.000-14.000)/24.000.
        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), ['variant_id' => $v6->id, 'price' => 24_000])
            ->assertOk()
            ->assertJson(['modal' => null, 'modal_induk' => 14_000, 'margin_pct' => 41.67]);

        // Isi modal varian 19.000 → margin sesungguhnya 20,83%.
        $this->patchJson(route('admin.prices.update', $product), ['variant_id' => $v6->id, 'price' => 24_000, 'cost_price' => 19_000])
            ->assertOk()
            ->assertJson(['modal' => 19_000, 'margin_pct' => 20.83]);
        $this->assertEquals(19_000, (float) $v6->fresh()->cost_price);

        // Varian 4mm² tetap fallback ke induk.
        $this->patchJson(route('admin.prices.update', $product), ['variant_id' => $v4->id, 'price' => 18_000])
            ->assertOk()->assertJson(['margin_pct' => 22.22]);
    }

    public function test_variant_of_another_product_is_rejected(): void
    {
        $a = $this->stockedProduct(0, ['product_type' => 'variable']);
        $b = $this->stockedProduct(0, ['product_type' => 'variable']);
        $foreign = $b->variants()->create(['sku' => 'FRX-1', 'name' => 'X', 'option_values' => ['U' => 'X'], 'price' => 100_000, 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $a), ['variant_id' => $foreign->id, 'price' => 1])
            ->assertNotFound();
    }

    /** Stok produk simple bisa di-set langsung; tercatat sebagai penyesuaian gudang. */
    public function test_simple_product_stock_can_be_set_inline(): void
    {
        $product = $this->stockedProduct(10, ['price' => 100_000]);

        $this->actingAs($this->katalog())
            ->patchJson(route('admin.prices.update', $product), ['price' => 100_000, 'stock' => 4])
            ->assertOk()
            ->assertJson(['stok' => 4]);

        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => -6]);
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
