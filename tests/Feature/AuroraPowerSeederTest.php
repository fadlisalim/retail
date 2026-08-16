<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\AuroraEchoAllInOneSeeder;
use Database\Seeders\AuroraEchoSeeder;
use Database\Seeders\AuroraPowerSeeder;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Katalog Aurora Power: harga, jalur beli, dan angka datasheet. */
class AuroraPowerSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Harga modal Agustus 2026 — acuan penguncian margin, tidak disimpan di repo produk. */
    private const COST = [
        'YF-FS-HP-1612-D' => 3_635_250, 'YF-FS-HP-4224-D' => 5_067_150, 'YF-FS-HP-6248' => 7_520_250,
        'YF-FS-HP-11048' => 16_167_150, 'YF-FS-HP-11048-D' => 13_136_850, 'YF-FS-WP1512' => 2_719_500,
        'YF-FS-WM3024' => 5_311_350, 'YF-FS-WM6348' => 7_742_250, 'YF-FS-WM-6348-P' => 8_186_250,
        'YF-FS-WM-12548-P' => 16_833_150, 'YF-TP-HV3P-40K' => 120_196_350, 'YF-TP-HV3P-50K' => 122_782_650,
        'YF-TP-HV3P-60K' => 128_399_250, 'YF-LV-P3E-8048' => 37_445_850, 'YF-LV-P3E-10048' => 38_800_050,
        'YF-LV-P3E-12048' => 40_009_950, 'YF-LV-P3E-15048' => 43_806_150, 'YF-LV-P3E-20048' => 56_437_950,
        'YF-LV-P3E-24048' => 61_466_250, 'YF-LFP-128200' => 7_492_500, 'YF-LFP-128314' => 8_491_500,
        'YF-LFP-256100' => 7_492_500, 'YF-LFP-5KWH-24V' => 14_979_450, 'YF-LFP-5KWH-48V' => 15_556_650,
        'YF-LFP-10.5KWH-48V' => 27_988_650, 'YF-LFP-15KWH-48V' => 32_106_750,
        'YF-LFP-21.5KWH-CES' => 60_911_250, 'YF-LFP-30KWH-CES' => 76_118_250,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BrandSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(AttributeSeeder::class);
        $this->seed(AuroraPowerSeeder::class);
    }

    private function catalogue()
    {
        return Product::whereIn('sku', array_keys(self::COST))->get();
    }

    public function test_it_seeds_the_whole_price_list(): void
    {
        $products = $this->catalogue();

        $this->assertCount(28, $products);

        foreach ($products as $product) {
            $this->assertSame('published', $product->status, $product->sku.' harus terbit');
            $this->assertGreaterThan(0, $product->weight_grams, $product->sku.' harus punya berat');
            $this->assertNotNull($product->category_id, $product->sku.' harus punya kategori');
        }
    }

    public function test_every_price_keeps_at_least_twenty_percent_margin(): void
    {
        foreach ($this->catalogue() as $product) {
            $this->assertGreaterThanOrEqual(
                self::COST[$product->sku] / 0.8,
                (float) $product->price,
                $product->sku.' di bawah margin 20%',
            );
        }
    }

    /**
     * Unit skala proyek tidak bisa dihitung ongkirnya otomatis, jadi harus
     * lewat jalur penawaran — bukan keranjang belanja.
     */
    public function test_project_scale_units_go_through_the_quotation_path(): void
    {
        $quotation = ['YF-TP-HV3P-40K', 'YF-LV-P3E-8048', 'YF-LV-P3E-24048', 'YF-LFP-21.5KWH-CES', 'YF-LFP-30KWH-CES'];
        foreach ($quotation as $sku) {
            $this->assertTrue((bool) Product::where('sku', $sku)->firstOrFail()->requires_quotation, $sku);
        }

        // Sebaliknya, unit rumah tangga tetap bisa dibeli langsung.
        foreach (['YF-FS-HP-1612-D', 'YF-LFP-128200', 'YF-LFP-15KWH-48V'] as $sku) {
            $this->assertFalse((bool) Product::where('sku', $sku)->firstOrFail()->requires_quotation, $sku);
        }
    }

    /** Angka datasheet yang mudah tertukar antar model sekeluarga. */
    public function test_datasheet_figures_are_not_mixed_up_between_models(): void
    {
        $specs = fn (string $sku) => Product::where('sku', $sku)->firstOrFail()->specifications;

        // Seri D dibedakan justru oleh yang TIDAK dimilikinya.
        $this->assertStringContainsString('Tidak tersedia (seri D)', $specs('YF-FS-HP-11048-D'));
        $this->assertStringContainsString('Maks. 9 unit', $specs('YF-FS-HP-11048'));

        // Dual MPPT: 2×7.500 W (non-D) vs 2×5.000 W (seri D).
        $this->assertStringContainsString('2 × 7.500 W', $specs('YF-FS-HP-11048'));
        $this->assertStringContainsString('2 × 5.000 W', $specs('YF-FS-HP-11048-D'));

        // WP1512 memakai PWM, sisanya MPPT.
        $this->assertStringContainsString('PWM', $specs('YF-FS-WP1512'));

        // Baterai 12,8V 200Ah dan 25,6V 100Ah beda batas seri/paralelnya.
        $this->assertStringContainsString('Maks. 4 seri', $specs('YF-LFP-128200'));
        $this->assertStringContainsString('Maks. 2 seri', $specs('YF-LFP-256100'));
    }

    public function test_a_seeded_product_page_opens(): void
    {
        $product = Product::where('sku', 'YF-FS-WM6348')->firstOrFail();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Transformer Base')
            ->assertSee('5.000 W');
    }

    public function test_running_it_again_does_not_duplicate(): void
    {
        $this->seed(AuroraPowerSeeder::class);

        $this->assertCount(28, $this->catalogue());
    }

    /** Lini ECHO yang sudah tayang ikut disesuaikan ke margin 20%. */
    public function test_it_reprices_the_existing_echo_line_and_adds_echo_16(): void
    {
        $this->seed(AuroraEchoSeeder::class);
        $this->seed(AuroraEchoAllInOneSeeder::class);

        // Harga lama memakai konvensi modal × 1,2 (margin 16,7%).
        $this->assertSame(31_890_000.0, (float) ProductVariant::where('sku', 'AURORA-ECHO-8')->firstOrFail()->price);

        $this->seed(AuroraPowerSeeder::class);

        $this->assertSame(7_050_000.0, (float) ProductVariant::where('sku', 'AURORA-ECHO-1')->firstOrFail()->price);
        $this->assertSame(10_700_000.0, (float) ProductVariant::where('sku', 'AURORA-ECHO-2')->firstOrFail()->price);
        $this->assertSame(37_450_000.0, (float) ProductVariant::where('sku', 'AURORA-ECHO-8')->firstOrFail()->price);
        $this->assertSame(66_600_000.0, (float) ProductVariant::where('sku', 'AURORA-ECHO-16')->firstOrFail()->price);
    }
}
