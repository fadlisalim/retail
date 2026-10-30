<?php

namespace Tests\Feature;

use App\Models\Product;
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
}
