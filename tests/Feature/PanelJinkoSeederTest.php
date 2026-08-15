<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PanelJinkoJkm580nSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Jinko 580 Wp module: seeded once, re-runnable, and browsable. */
class PanelJinkoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->seed(PanelJinkoJkm580nSeeder::class);
    }

    private function panel(): Product
    {
        return Product::where('sku', 'JINKO-JKM580N-72HL4V')->firstOrFail();
    }

    public function test_it_seeds_the_panel_with_datasheet_figures(): void
    {
        $panel = $this->panel();

        $this->assertSame(1_900_000.0, (float) $panel->price);
        $this->assertSame('published', $panel->status);
        // 27 kg per keping — panel selalu lewat kargo, bukan kurir biasa.
        $this->assertSame(27000, $panel->weight_grams);
        $this->assertTrue((bool) $panel->requires_freight);
        $this->assertStringContainsString('30 Tahun', $panel->warranty);
        // Kolom STC yang dipakai harus kolom 580 Wp, bukan varian lain.
        $this->assertStringContainsString('52,31 V', $panel->specifications);
        $this->assertStringContainsString('22,45%', $panel->specifications);
    }

    public function test_the_product_page_opens(): void
    {
        $this->get(route('products.show', $this->panel()->slug))
            ->assertOk()
            ->assertSee('580 Wp')
            ->assertSee('TOPCon');
    }

    public function test_running_it_again_does_not_duplicate(): void
    {
        $this->seed(PanelJinkoJkm580nSeeder::class);

        $this->assertSame(1, Product::where('sku', 'JINKO-JKM580N-72HL4V')->count());
    }
}
