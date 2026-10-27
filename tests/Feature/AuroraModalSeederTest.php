<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\AuroraModalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Modal Aurora = EPC × 1,11 (PPN); harga jual tidak berubah; modal isian admin tidak ditimpa. */
class AuroraModalSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_cost_with_ppn_without_touching_selling_price_or_admin_costs(): void
    {
        $inv = Product::factory()->create(['sku' => 'YF-FS-HP-6248', 'price' => 9_450_000, 'cost_price' => null]);
        $bat = Product::factory()->create(['sku' => 'YF-LFP-128200', 'price' => 9_400_000, 'cost_price' => 7_000_000]); // diisi admin
        $echo = Product::factory()->create(['sku' => 'AURORA-ECHO', 'product_type' => 'variable', 'cost_price' => null]);
        $echo1 = ProductVariant::create(['product_id' => $echo->id, 'sku' => 'AURORA-ECHO-1', 'name' => 'ECHO-1', 'price' => 6_950_000, 'is_active' => true]);

        $this->seed(AuroraModalSeeder::class);

        $this->assertSame(7_431_450, (int) $inv->fresh()->cost_price);
        $this->assertSame(9_450_000, (int) $inv->fresh()->price);
        $this->assertSame(7_000_000, (int) $bat->fresh()->cost_price);
        $this->assertSame(5_772_000, (int) $echo1->fresh()->cost_price);
        $this->assertSame(5_772_000, (int) $echo->fresh()->cost_price);
    }
}
