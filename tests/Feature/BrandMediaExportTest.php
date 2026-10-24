<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin bisa mengunduh semua foto & dokumen produk satu brand sebagai ZIP. */
class BrandMediaExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_downloads_brand_photos_and_documents_as_zip_grouped_per_product(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/a.webp', 'foto-a');
        Storage::disk('public')->put('products/b.webp', 'foto-b');
        Storage::disk('public')->put('products/v.webp', 'foto-v');
        Storage::disk('public')->put('products/docs/brosur.pdf', '%PDF');

        $brand = Brand::create(['name' => 'Aurora', 'slug' => 'aurora', 'is_active' => true]);
        $product = Product::factory()->create(['brand_id' => $brand->id, 'name' => 'Inverter Aurora 6kW', 'main_image_path' => 'products/a.webp']);
        $product->images()->create(['path' => 'products/a.webp', 'alt' => 'a', 'sort_order' => 1]);
        $product->images()->create(['path' => 'products/b.webp', 'alt' => 'b', 'sort_order' => 2]);
        $product->documents()->create(['type' => 'brosur', 'title' => 'Datasheet 6kW', 'path' => 'products/docs/brosur.pdf']);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'AU-6', 'name' => '6 kW', 'price' => 1, 'is_active' => true, 'image_path' => 'products/v.webp']);
        $empty = Brand::create(['name' => 'Kosong', 'slug' => 'kosong', 'is_active' => true]);

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        $this->actingAs($admin)->get(route('admin.brands.index'))->assertOk()->assertSee('Unduh foto &amp; dokumen (ZIP)', false);

        $response = $this->actingAs($admin)->get(route('admin.brands.media', $brand))->assertOk();
        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
        $this->assertSame([
            'inverter-aurora-6kw/dokumen/datasheet-6kw.pdf',
            'inverter-aurora-6kw/foto-varian/6-kw.webp',
            'inverter-aurora-6kw/foto/a.webp',
            'inverter-aurora-6kw/foto/b.webp',
        ], $names);
        $zip->close();

        $this->actingAs($admin)->from(route('admin.brands.index'))->get(route('admin.brands.media', $empty))
            ->assertRedirect(route('admin.brands.index'))->assertSessionHas('warning');
    }
}
