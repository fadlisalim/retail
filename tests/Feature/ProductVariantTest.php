<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());

        return $user;
    }

    public function test_admin_can_add_edit_and_delete_variants(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(0, ['product_type' => 'simple', 'price' => 1000000]);

        // Add a variant with its own price, stock, and image.
        $this->post(route('admin.products.variant.store', $product), [
            'name' => 'AMAL 4000',
            'price' => 34900000,
            'stock' => 5,
            'image' => UploadedFile::fake()->image('amal4000.jpg'),
        ])->assertRedirect();

        $product->refresh();
        $variant = $product->variants->first();
        $this->assertNotNull($variant);
        $this->assertEquals(34900000, (float) $variant->price);
        $this->assertEquals(5, $variant->stock);
        $this->assertNotNull($variant->image_path);
        Storage::disk('public')->assertExists($variant->image_path);
        // Adding a variant makes the product variable.
        $this->assertSame('variable', $product->product_type);

        // Edit: change price + set stock to 12.
        $this->put(route('admin.products.variant.update', $variant), [
            'name' => 'AMAL 4000',
            'price' => 35900000,
            'stock' => 12,
        ])->assertRedirect();
        $variant->refresh();
        $this->assertEquals(35900000, (float) $variant->price);
        $this->assertEquals(12, $variant->stock);

        // Delete.
        $this->delete(route('admin.products.variant.destroy', $variant))->assertRedirect();
        $this->assertEquals(0, $product->fresh()->variants()->count());
    }

    public function test_variant_weight_and_dimensions_are_saved(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(0, ['product_type' => 'simple', 'price' => 500000]);

        $this->post(route('admin.products.variant.store', $product), [
            'name' => '100 Wp',
            'price' => 500000,
            'stock' => 3,
            'weight_grams' => 12500,
            'length_cm' => 102,
            'width_cm' => 67,
            'height_cm' => 3,
        ])->assertRedirect();

        $variant = $product->fresh()->variants->first();
        $this->assertSame(12500, $variant->weightGrams());
        $this->assertEqualsWithDelta(102 * 67 * 3, $variant->volumeCm3(), 0.01);
    }

    public function test_variant_dimensions_fall_back_to_product_when_blank(): void
    {
        $product = Product::factory()->create([
            'weight_grams' => 8000, 'length_cm' => 50, 'width_cm' => 40, 'height_cm' => 5,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V-FALLBACK', 'name' => 'X',
            'price' => 1000, 'is_active' => true, 'sort_order' => 1,
        ]);

        // No variant-specific values → inherits the product's weight & volume.
        $this->assertSame(8000, $variant->weightGrams());
        $this->assertEqualsWithDelta(50 * 40 * 5, $variant->volumeCm3(), 0.01);
    }
}
