<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\AuroraPjuSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** PJU Aurora: modal = EPC × 1,11 (PPN), jual = modal ÷ 0,78 bulat ke atas 50 rb; LS & LS-D tayang, sisanya draf. */
class AuroraPjuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_aurora_street_lights_with_ppn_inclusive_cost_and_22_percent_margin(): void
    {
        Storage::fake('public');
        $this->seed(CategorySeeder::class);
        $this->seed(AuroraPjuSeeder::class);

        $this->assertSame(5_605_500, AuroraPjuSeeder::cost(5_050_000));
        $this->assertSame(7_200_000, AuroraPjuSeeder::sellingPrice(5_605_500));

        $v = ProductVariant::where('sku', 'YF-OPTIMUS-80W-LS')->firstOrFail();
        $this->assertSame(5_605_500, (int) $v->cost_price);
        $this->assertSame(7_200_000, (int) $v->price);
        $this->assertSame(4_150_000, (int) ProductVariant::where('sku', 'YF-OPTIMUS-80W-LS-D')->value('price'));
        $this->assertSame(16, ProductVariant::whereIn('product_id', Product::where('sku', 'like', 'AUR-%')->pluck('id'))->count());

        // Margin ≥ 22% di semua varian.
        foreach (ProductVariant::whereIn('product_id', Product::where('sku', 'like', 'AUR-%')->pluck('id'))->get() as $variant) {
            $this->assertGreaterThanOrEqual(0.22, ($variant->price - $variant->cost_price) / $variant->price, $variant->sku);
        }

        $ls = Product::where('sku', 'AUR-OPTIMUS-LS')->firstOrFail();
        $this->assertSame('published', $ls->status);
        $this->assertNotNull($ls->main_image_path);
        $this->assertSame(5, $ls->images()->count());
        $this->assertSame(1, $ls->documents()->count());
        $lsd = Product::where('sku', 'AUR-OPTIMUS-LS-D')->firstOrFail();
        $this->assertNotNull(ProductVariant::where('sku', 'YF-OPTIMUS-120W-LS-D')->value('image_path'));
        $this->assertSame(1, $lsd->documents()->count());
        $this->assertSame('draft', Product::where('sku', 'AUR-FANCY')->value('status'));
        $this->assertTrue($ls->categories->contains('slug', 'pju-tenaga-surya'));

        // Jalan ulang: suntingan admin aman, tidak dobel.
        $ls->forceFill(['price' => 6_999_000])->save();
        $this->seed(AuroraPjuSeeder::class);
        $this->assertSame(6_999_000, (int) $ls->fresh()->price);
        $this->assertSame(5, $ls->images()->count());

        $this->get(route('products.show', $ls->slug))->assertOk()->assertSee('PJU Tenaga Surya Aurora Optimus LS')->assertSee('Rp 6.999.000')->assertDontSee('5.605.500');
    }
}
