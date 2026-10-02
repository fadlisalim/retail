<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\PompaHybridLarensSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pompa Air Tenaga Surya Hybrid LARENS 750W / 2200W + kontroler (Okt 2026). */
class PompaHybridLarensSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_hybrid_pump_with_ten_models_priced_per_wattage(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $parent = Category::factory()->create(['name' => 'Pompa Air Tenaga Surya', 'slug' => 'pompa-air-tenaga-surya']);
        Category::factory()->create(['name' => 'Submersible', 'slug' => 'pompa-air-tenaga-surya-submersible', 'parent_id' => $parent->id]);

        $this->seed(PompaHybridLarensSeeder::class);

        $p = Product::where('sku', 'LARENS-PSS-HYBRID')->firstOrFail();
        $this->assertSame('variable', $p->product_type);
        $this->assertSame('pompa-air-tenaga-surya-submersible', $p->category->slug);
        $this->assertSame(10, $p->variants()->count());
        $this->assertEquals(13_450_000, $p->cost_price);
        $this->assertEquals(17_940_000, $p->price); // 13,45 jt ÷ 0,75 → bulat ke atas 10.000
        $this->assertNotNull($p->main_image_path);
        Storage::disk('public')->assertExists($p->main_image_path);

        $v750 = ProductVariant::where('sku', 'LARENS-4PSS150-30-96-750-H')->firstOrFail();
        $this->assertSame('750W · 15,0 m³/jam · head 30 m', $v750->name);
        $this->assertEquals(17_940_000, $v750->price);
        $this->assertEquals(13_450_000, $v750->cost_price);

        $v2200 = ProductVariant::where('sku', 'LARENS-6PSS250-45-280-2200-H')->firstOrFail();
        $this->assertEquals(21_950_000, $v2200->price);
        $this->assertEquals(16_458_000, $v2200->cost_price);
        $this->assertSame(34000, $v2200->weightGrams());
        $this->assertSame(2, (int) $v2200->fresh()->stock);

        $this->get(route('products.show', $p->slug))->assertOk()->assertSee('4PSS3.5/260-280/2200-H')->assertSee('Hybrid');

        // Idempotent: harga editan admin tidak ditimpa, varian hilang dilengkapi.
        $v750->update(['price' => 18_500_000]);
        ProductVariant::where('sku', 'LARENS-4PSS30-100-96-750-H')->delete();
        $this->seed(PompaHybridLarensSeeder::class);
        $this->assertEquals(18_500_000, $v750->fresh()->price);
        $this->assertSame(10, $p->variants()->count());
        $this->assertSame(1, Product::where('sku', 'LARENS-PSS-HYBRID')->count());
    }
}
