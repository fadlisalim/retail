<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\AuroraHargaJualSeeder;
use Database\Seeders\AuroraModalSeeder;
use Database\Seeders\HargaPemilikOkt27Seeder;
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

    public function test_selling_price_set_to_22_percent_margin(): void
    {
        $inv = Product::factory()->create(['sku' => 'YF-FS-HP-1612-D', 'price' => 4_550_000, 'sale_price' => 4_900_000]);
        $echo = Product::factory()->create(['sku' => 'AURORA-ECHO', 'product_type' => 'variable']);
        $e1 = ProductVariant::create(['product_id' => $echo->id, 'sku' => 'AURORA-ECHO-1', 'name' => 'ECHO-1', 'price' => 6_950_000, 'is_active' => true]);
        $e8 = ProductVariant::create(['product_id' => $echo->id, 'sku' => 'AURORA-ECHO-8', 'name' => 'ECHO-8', 'price' => 37_450_000, 'is_active' => true]);

        $this->seed(AuroraHargaJualSeeder::class);

        $inv = $inv->fresh();
        $this->assertSame(4_850_000, (int) $inv->price);
        $this->assertSame(3_774_000, (int) $inv->cost_price);
        $this->assertNull($inv->sale_price);
        $this->assertSame(7_400_000, (int) $e1->fresh()->price);
        $this->assertSame(38_400_000, (int) $e8->fresh()->price);
        $this->assertSame(7_400_000, (int) $echo->fresh()->price);
        foreach ([$inv, $e1->fresh(), $e8->fresh()] as $m) {
            $this->assertGreaterThanOrEqual(0.22, ($m->price - $m->cost_price) / $m->price);
        }
    }

    public function test_owner_prices_for_echo_8_and_aiko(): void
    {
        $echo = Product::factory()->create(['sku' => 'AURORA-ECHO', 'product_type' => 'variable', 'price' => 7_400_000]);
        ProductVariant::create(['product_id' => $echo->id, 'sku' => 'AURORA-ECHO-1', 'name' => 'ECHO-1', 'price' => 7_400_000, 'is_active' => true]);
        $e8 = ProductVariant::create(['product_id' => $echo->id, 'sku' => 'AURORA-ECHO-8', 'name' => 'ECHO-8', 'price' => 38_400_000, 'is_active' => true]);
        $aiko = Product::factory()->create(['sku' => 'AIKO-COMET2U', 'product_type' => 'variable', 'price' => 2_490_000]);
        $a640 = ProductVariant::create(['product_id' => $aiko->id, 'sku' => 'AIKO-COMET2U-640', 'name' => '640 Wp', 'price' => 2_490_000, 'sale_price' => 2_400_000, 'is_active' => true]);

        $this->seed(HargaPemilikOkt27Seeder::class);

        $this->assertSame(33_250_000, (int) $e8->fresh()->price);
        $this->assertSame(7_400_000, (int) $echo->fresh()->price);
        $this->assertSame(2_750_000, (int) $a640->fresh()->price);
        $this->assertSame(2_400_000, (int) $a640->fresh()->sale_price); // diskon admin tetap
        $this->assertSame(2_750_000, (int) $aiko->fresh()->price);
    }
}
