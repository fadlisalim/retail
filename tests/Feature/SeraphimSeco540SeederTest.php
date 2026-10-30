<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\SeraphimSeco540Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Seraphim Seco 540Wp: harga/modal/stok dari pemilik; jalan ulang tidak menimpa suntingan admin. */
class SeraphimSeco540SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_product_with_owner_price_cost_stock_photo_and_datasheet(): void
    {
        Storage::fake('public');

        $this->seed(SeraphimSeco540Seeder::class);

        $p = Product::where('sku', SeraphimSeco540Seeder::SKU)->firstOrFail();
        $this->assertSame(1_600_000, (int) $p->price);
        $this->assertSame(1_050_000, (int) $p->cost_price);
        $this->assertSame(50, (int) $p->fresh()->stock);
        $this->assertSame('published', $p->status);
        $this->assertSame('Garansi 10 tahun', $p->warranty);
        $this->assertNotNull($p->main_image_path);
        Storage::disk('public')->assertExists($p->main_image_path);
        $this->assertSame(1, $p->images()->count());
        $this->assertSame(1, $p->documents()->count());

        $this->get(route('products.show', $p->slug))->assertOk()->assertSee('540Wp');
    }

    public function test_rerun_keeps_admin_edits_and_stock(): void
    {
        Storage::fake('public');
        $this->seed(SeraphimSeco540Seeder::class);
        $p = Product::where('sku', SeraphimSeco540Seeder::SKU)->firstOrFail();
        $p->forceFill(['price' => 1_550_000])->save();
        $p->images()->delete();

        $this->seed(SeraphimSeco540Seeder::class);

        $p->refresh();
        $this->assertSame(1_550_000, (int) $p->price);
        $this->assertSame(50, (int) $p->stock);
        $this->assertSame(0, $p->images()->count());
        $this->assertSame(1, $p->documents()->count());
    }
}
