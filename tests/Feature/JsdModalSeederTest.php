<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\JsdHargaJualSeeder;
use Database\Seeders\JsdModalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Modal JSD = harga pricelist tanpa PPN; harga jual tetap; modal isian admin tidak ditimpa. */
class JsdModalSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_cost_without_ppn_and_keeps_selling_price_and_admin_costs(): void
    {
        $inv = Product::factory()->create(['sku' => 'JSD-J1200HC', 'price' => 3_100_000, 'cost_price' => null]);
        $bat = Product::factory()->create(['sku' => 'JSD-BG48100', 'price' => 15_000_000, 'cost_price' => 11_000_000]); // diisi admin

        $this->seed(JsdModalSeeder::class);

        $this->assertSame(2_450_000, (int) $inv->fresh()->cost_price);
        $this->assertSame(3_100_000, (int) $inv->fresh()->price);
        $this->assertSame(11_000_000, (int) $bat->fresh()->cost_price);
    }

    public function test_selling_price_set_to_25_percent_margin(): void
    {
        $inv = Product::factory()->create(['sku' => 'JSD-J1200HC', 'price' => 3_100_000, 'sale_price' => 3_200_000]);
        $bat = Product::factory()->create(['sku' => 'JSD-LD48314', 'price' => 34_600_000, 'sale_price' => 37_000_000]);

        $this->seed(JsdHargaJualSeeder::class);

        $inv = $inv->fresh();
        $this->assertSame(3_300_000, (int) $inv->price);
        $this->assertSame(2_450_000, (int) $inv->cost_price);
        $this->assertSame(3_200_000, (int) $inv->sale_price); // diskon admin tetap (masih di bawah harga baru)
        $bat = $bat->fresh();
        $this->assertSame(36_900_000, (int) $bat->price);
        $this->assertNull($bat->sale_price);
        foreach ([$inv, $bat] as $m) {
            $this->assertGreaterThanOrEqual(0.25, ($m->price - $m->cost_price) / $m->price);
        }
    }
}
