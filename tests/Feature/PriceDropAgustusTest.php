<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\BateraiPjuLithiumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Penurunan harga Agustus 2026: baterai PJU clearance 1,95jt → 1,25jt. */
class PriceDropAgustusTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'baterai-lithium-pju-tenaga-surya-128v-60ah-clearance';

    public function test_a_fresh_install_gets_the_new_clearance_price(): void
    {
        $this->seed(BateraiPjuLithiumSeeder::class);

        $product = Product::where('slug', self::SLUG)->firstOrFail();
        $this->assertEquals(2_700_000, (float) $product->price);
        $this->assertEquals(1_250_000, (float) $product->sale_price);
    }

    /** Baris lama yang masih 1,95jt diturunkan; harga hasil editan admin tidak disentuh. */
    public function test_existing_rows_are_lowered_but_admin_edits_are_kept(): void
    {
        $this->seed(BateraiPjuLithiumSeeder::class);
        $stillOld = Product::where('slug', self::SLUG)->firstOrFail();
        $stillOld->forceFill(['sale_price' => 1_950_000])->save();   // simulasi DB produksi lama

        $this->seed(BateraiPjuLithiumSeeder::class);
        $this->assertEquals(1_250_000, (float) $stillOld->fresh()->sale_price);

        // Admin sudah menyetel harga lain → seeder ulang tidak menimpanya.
        $stillOld->forceFill(['sale_price' => 1_500_000])->save();
        $this->seed(BateraiPjuLithiumSeeder::class);
        $this->assertEquals(1_500_000, (float) $stillOld->fresh()->sale_price);
    }
}
