<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\JsdSolarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The JSD Solar catalogue: seeded once, re-runnable, and browsable. */
class JsdSolarSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->seed(JsdSolarSeeder::class);
    }

    private function products()
    {
        return Product::whereHas('brand', fn ($q) => $q->where('slug', 'jsd-solar'))->get();
    }

    public function test_it_seeds_the_whole_ready_stock_line_up(): void
    {
        $products = $this->products();

        $this->assertCount(18, $products);
        $this->assertCount(18, $products->pluck('sku')->unique());
        $this->assertCount(18, $products->pluck('slug')->unique());

        foreach ($products as $product) {
            $this->assertSame('published', $product->status, $product->sku.' harus terbit');
            $this->assertGreaterThan(0, $product->price, $product->sku.' harus punya harga');
            // Ongkir dihitung dari berat, jadi tidak boleh ada yang kosong.
            $this->assertGreaterThan(0, $product->weight_grams, $product->sku.' harus punya berat');
            $this->assertNotEmpty($product->warranty, $product->sku.' harus punya garansi');
            $this->assertNotNull($product->category_id, $product->sku.' harus punya kategori');
        }
    }

    /** Harga jual = modal ÷ 0,8, dibulatkan ke ATAS — margin tidak boleh < 20%. */
    public function test_every_price_keeps_at_least_twenty_percent_margin(): void
    {
        $cost = [
            'JSD-J1200HC' => 2_450_000, 'JSD-J2500HC' => 2_975_000, 'JSD-J4000E' => 4_950_000,
            'JSD-J6500HC' => 5_850_000, 'JSD-J6200HP' => 6_750_000, 'JSD-J11100HPC' => 9_750_000,
            'JSD-WIFI-PLUG-PRO' => 450_000, 'JSD-XHP12K15' => 8_350_000, 'JSD-XHP4K30' => 14_250_000,
            'JSD-JHP5000' => 22_500_000, 'JSD-XHP65K60' => 23_000_000, 'JSD-J12100' => 3_050_000,
            'JSD-J12200' => 5_950_000, 'JSD-J24100' => 5_950_000, 'JSD-BG48100' => 12_000_000,
            'JSD-J4U48100' => 12_000_000, 'JSD-LFP48200' => 23_000_000, 'JSD-LD48314' => 27_650_000,
        ];

        foreach ($this->products() as $product) {
            $modal = $cost[$product->sku] ?? null;
            $this->assertNotNull($modal, 'Harga modal acuan untuk '.$product->sku.' belum terdaftar di test.');
            $this->assertGreaterThanOrEqual($modal / 0.8, (float) $product->price, $product->sku.' di bawah margin 20%');
        }
    }

    public function test_a_seeded_product_page_opens(): void
    {
        $product = Product::where('sku', 'JSD-J6500HC')->firstOrFail();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('J6500HC')
            ->assertSee('MPPT');
    }

    public function test_running_it_again_does_not_duplicate(): void
    {
        $this->seed(JsdSolarSeeder::class);

        $this->assertCount(18, $this->products());
    }
}
