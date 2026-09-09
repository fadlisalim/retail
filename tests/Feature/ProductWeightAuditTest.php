<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\MountingKabelSeeder;
use Database\Seeders\PaketApex300Seeder;
use Database\Seeders\PaketEcho8Hybrid4kwpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Audit berat & dimensi (Sep 2026): L-Feet 250 g → 100 g, clamp 90 g →
 * 55/60 g (koreksi hanya bila masih nilai seeder), paket ratusan kg wajib
 * kargo, dan command product:audit-weight menangkap angka yang janggal.
 */
class ProductWeightAuditTest extends TestCase
{
    use RefreshDatabase;

    private function mountingCategories(): void
    {
        $this->defaultWarehouse();
        Category::factory()->create(['name' => 'Mounting & Rangka', 'slug' => 'mounting-rangka']);
        Category::factory()->create(['name' => 'Kabel, Konektor & Proteksi', 'slug' => 'kabel-konektor-proteksi']);
        Category::factory()->create(['name' => 'Konektor MC4', 'slug' => 'kabel-konektor-proteksi-konektor-mc4']);
    }

    public function test_fresh_seed_uses_realistic_accessory_weights(): void
    {
        $this->mountingCategories();
        $this->seed(MountingKabelSeeder::class);

        $lfeet = Product::where('slug', 'tile-hook-antai-atl-fwny-05-l-feet')->firstOrFail();
        $this->assertSame(100, (int) $lfeet->weight_grams);
        $this->assertEquals([6, 5, 4], [(int) $lfeet->length_cm, (int) $lfeet->width_cm, (int) $lfeet->height_cm]);
        $this->assertSame(55, (int) Product::where('slug', 'end-clamp-antai-cg-018-35-40')->value('weight_grams'));
        $this->assertSame(60, (int) Product::where('slug', 'mid-clamp-antai-gn-003')->value('weight_grams'));
    }

    /** Produksi masih memakai 250 g dari seeder awal → dikoreksi; angka editan admin dibiarkan. */
    public function test_rerun_corrects_only_rows_still_on_the_old_seeded_weight(): void
    {
        $this->mountingCategories();
        $this->seed(MountingKabelSeeder::class);

        Product::where('slug', 'tile-hook-antai-atl-fwny-05-l-feet')->update(['weight_grams' => 250, 'length_cm' => 12, 'width_cm' => 6, 'height_cm' => 6]);
        Product::where('slug', 'end-clamp-antai-cg-018-35-40')->update(['weight_grams' => 90]);
        Product::where('slug', 'mid-clamp-antai-gn-003')->update(['weight_grams' => 75]); // sudah ditimbang admin

        $this->seed(MountingKabelSeeder::class);

        $lfeet = Product::where('slug', 'tile-hook-antai-atl-fwny-05-l-feet')->firstOrFail();
        $this->assertSame(100, (int) $lfeet->weight_grams);
        $this->assertSame(6, (int) $lfeet->length_cm);
        $this->assertSame(55, (int) Product::where('slug', 'end-clamp-antai-cg-018-35-40')->value('weight_grams'));
        $this->assertSame(75, (int) Product::where('slug', 'mid-clamp-antai-gn-003')->value('weight_grams'));
    }

    public function test_heavy_bundles_are_flagged_for_freight_even_when_they_already_exist(): void
    {
        $this->defaultWarehouse();
        $this->seed(PaketEcho8Hybrid4kwpSeeder::class);
        $this->seed(PaketApex300Seeder::class);

        foreach (['paket-plts-hybrid-aurora-echo-8-solar-4kwp', 'paket-plts-bluetti-apex-300-solar-1200wp'] as $slug) {
            Product::where('slug', $slug)->update(['requires_freight' => false]); // kondisi produksi (versi awal)
        }

        $this->seed(PaketEcho8Hybrid4kwpSeeder::class);
        $this->seed(PaketApex300Seeder::class);

        foreach (['paket-plts-hybrid-aurora-echo-8-solar-4kwp', 'paket-plts-bluetti-apex-300-solar-1200wp'] as $slug) {
            $this->assertTrue((bool) Product::where('slug', $slug)->value('requires_freight'), $slug);
        }
    }

    public function test_audit_command_reports_suspicious_weights_and_dimensions(): void
    {
        $this->defaultWarehouse();

        // Kabel per meter dengan kotak placeholder 20×20×20 → volumetrik 1,33 kg per meter.
        $cable = Product::factory()->create([
            'name' => 'Kabel Demo (per meter)', 'unit' => 'meter', 'price' => 18000,
            'weight_grams' => 80, 'length_cm' => 20, 'width_cm' => 20, 'height_cm' => 20,
        ]);
        // Paket 150 kg tanpa kargo.
        $paket = Product::factory()->create([
            'name' => 'Paket Demo Berat', 'unit' => 'paket', 'product_type' => 'variable', 'price' => 30000000,
            'weight_grams' => 150000, 'length_cm' => 0, 'width_cm' => 0, 'height_cm' => 0, 'requires_freight' => false,
        ]);
        ProductVariant::create([
            'product_id' => $paket->id, 'sku' => 'PAKET-DEMO-A', 'name' => 'A', 'option_values' => ['Paket' => 'A'],
            'price' => 30000000, 'is_active' => true, 'sort_order' => 1,
        ]);
        // Aksesoris murah tapi 250 g (kasus L-Feet).
        Product::factory()->create(['name' => 'Bracket Demo', 'unit' => 'pcs', 'price' => 21300, 'weight_grams' => 250, 'length_cm' => 6, 'width_cm' => 5, 'height_cm' => 4]);
        // Wajar → tidak dilaporkan.
        Product::factory()->create(['name' => 'Panel Wajar', 'unit' => 'pcs', 'price' => 2490000, 'weight_grams' => 27100, 'length_cm' => 238, 'width_cm' => 113, 'height_cm' => 3, 'requires_freight' => true]);

        // expectsOutputToContain hanya mencocokkan satu substring per baris
        // tulisan, sedangkan satu baris tabel memuat beberapa temuan.
        $this->assertSame(0, Artisan::call('product:audit-weight'));
        $output = Artisan::output();

        foreach (['Kabel Demo', 'PER METER', 'placeholder 20×20×20', 'belum diset kargo', 'varian paket memakai berat induk', 'aksesoris murah tapi berat'] as $needle) {
            $this->assertStringContainsString($needle, $output);
        }
        $this->assertStringNotContainsString('Panel Wajar', $output);
    }
}
