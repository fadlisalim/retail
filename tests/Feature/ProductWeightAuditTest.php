<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ShippingService;
use Database\Seeders\MountingKabelSeeder;
use Database\Seeders\PaketAmalSeeder;
use Database\Seeders\PaketApex300Seeder;
use Database\Seeders\PaketEcho8Hybrid4kwpSeeder;
use Database\Seeders\PaketPowerhomeSolarSeeder;
use Database\Seeders\VarianBeratDimensiSeeder;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Produksi: slug paket Bezvolt sudah diedit admin → seeder lama (cari slug)
     * mencoba insert ulang dan gagal "Duplicate entry" di SKU. Sekarang dicari
     * per SKU, data editan admin dibiarkan, hanya flag kargo yang dilengkapi.
     */
    public function test_bezvolt_bundle_seeder_finds_the_existing_product_by_sku(): void
    {
        $this->defaultWarehouse();
        $this->seed(PaketPowerhomeSolarSeeder::class);

        Product::where('sku', 'PAKET-PH605-SOLAR')->update([
            'slug' => 'paket-bezvolt-power-home-6kw-edit-admin',
            'name' => 'Paket Bezvolt Power Home 6 kW (Edit Admin)',
            'requires_freight' => false,
        ]);

        $this->seed(PaketPowerhomeSolarSeeder::class);

        $this->assertSame(1, Product::where('sku', 'PAKET-PH605-SOLAR')->count());
        $product = Product::where('sku', 'PAKET-PH605-SOLAR')->firstOrFail();
        $this->assertSame('paket-bezvolt-power-home-6kw-edit-admin', $product->slug);
        $this->assertSame('Paket Bezvolt Power Home 6 kW (Edit Admin)', $product->name);
        $this->assertTrue((bool) $product->requires_freight);
        $this->assertCount(6, $product->variants()->where('is_active', true)->get());
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

    /** Kasus Paket Amal: 3 varian beda harga, tapi berat & dimensi jatuh ke induk (kembar). */
    public function test_audit_flags_twin_variants_but_not_identical_panels(): void
    {
        $this->defaultWarehouse();

        $amal = Product::factory()->create([
            'name' => 'Paket Kembar', 'unit' => 'paket', 'product_type' => 'variable', 'price' => 24900000,
            'weight_grams' => 80000, 'length_cm' => 100, 'width_cm' => 80, 'height_cm' => 60, 'requires_freight' => true,
        ]);
        foreach ([['2000', 24900000], ['4000', 34900000], ['8000', 54900000]] as $i => [$label, $price]) {
            ProductVariant::create([
                'product_id' => $amal->id, 'sku' => 'KEMBAR-'.$label, 'name' => 'AMAL '.$label, 'option_values' => ['Paket' => $label],
                'price' => $price, 'is_active' => true, 'sort_order' => $i,
            ]);
        }

        // Berat beda jauh tapi dimensi semua varian sama (jatuh ke induk).
        $batt = Product::factory()->create([
            'name' => 'Baterai Dimensi Sama', 'unit' => 'unit', 'product_type' => 'variable', 'price' => 17900000,
            'weight_grams' => 48000, 'length_cm' => 44, 'width_cm' => 42, 'height_cm' => 22, 'requires_freight' => true,
        ]);
        foreach ([['5kwh', 17900000, 48000], ['10kwh', 33900000, 92000]] as $i => [$label, $price, $weight]) {
            ProductVariant::create([
                'product_id' => $batt->id, 'sku' => 'BATT-'.$label, 'name' => $label, 'option_values' => ['Kapasitas' => $label],
                'price' => $price, 'weight_grams' => $weight, 'is_active' => true, 'sort_order' => $i,
            ]);
        }

        // Panel 640–670 Wp: harga & fisik memang sama → tidak dilaporkan.
        $panel = Product::factory()->create([
            'name' => 'Panel Sama Persis', 'unit' => 'pcs', 'product_type' => 'variable', 'price' => 2490000,
            'weight_grams' => 27100, 'length_cm' => 238, 'width_cm' => 113, 'height_cm' => 3, 'requires_freight' => true,
        ]);
        foreach (['640', '650', '660'] as $i => $wp) {
            ProductVariant::create([
                'product_id' => $panel->id, 'sku' => 'PNL-'.$wp, 'name' => $wp.' Wp', 'option_values' => ['Daya' => $wp],
                'price' => 2490000, 'is_active' => true, 'sort_order' => $i,
            ]);
        }

        $this->assertSame(0, Artisan::call('product:audit-weight'));
        $output = Artisan::output();

        $this->assertStringContainsString('Paket Kembar', $output);
        $this->assertStringContainsString('3 varian beda harga (24,9 jt–54,9 jt) tapi beratnya sama semua (80,0 kg)', $output);
        $this->assertStringContainsString('Baterai Dimensi Sama', $output);
        $this->assertStringContainsString('2 varian beratnya beda (48,0–92,0 kg) tapi dimensinya sama semua (44×42×22)', $output);
        $this->assertStringNotContainsString('Panel Sama Persis', $output);
    }

    public function test_paket_amal_variants_get_their_own_weight_and_dimensions(): void
    {
        $this->defaultWarehouse();
        $this->seed(PaketAmalSeeder::class);

        $product = Product::where('sku', 'PAKET-AMAL')->firstOrFail();
        $this->assertSame(100000, (int) $product->weight_grams);
        $this->assertSame(6, (int) $product->package_count);

        $weights = $product->variants()->orderBy('sort_order')->get()->map(fn ($v) => $v->weightGrams())->all();
        $this->assertSame([100000, 150000, 290000], $weights);
        $dims = $product->variants()->orderBy('sort_order')->get()->map(fn ($v) => $v->lengthCm().'×'.$v->widthCm().'×'.$v->heightCm())->all();
        $this->assertSame(['230×115×12', '230×115×17', '230×115×31'], $dims);

        // Setelah diisi, audit tidak lagi menandai varian Amal sebagai kembar.
        Artisan::call('product:audit-weight');
        $this->assertStringNotContainsString('Paket PLTS Amal', Artisan::output());

        // Angka editan admin (Edit Cepat Produk) tidak ditimpa saat seeder diulang.
        ProductVariant::where('sku', 'PAKET-AMAL-4000')->update(['weight_grams' => 162000]);
        ProductVariant::where('sku', 'PAKET-AMAL-8000')->update(['length_cm' => 240, 'width_cm' => 120, 'height_cm' => 40]);
        $this->seed(PaketAmalSeeder::class);
        $this->assertSame(162000, (int) ProductVariant::where('sku', 'PAKET-AMAL-4000')->value('weight_grams'));
        $this->assertSame(240, (int) ProductVariant::where('sku', 'PAKET-AMAL-8000')->value('length_cm'));
    }

    /** Produksi: varian Power Home / Apex / ECHO sudah ada tanpa dimensi → dilengkapi, yang sudah diisi admin dibiarkan. */
    public function test_variant_shipping_seeder_fills_only_empty_columns(): void
    {
        $this->defaultWarehouse();
        $this->seed(PaketPowerhomeSolarSeeder::class);
        $this->seed(PaketApex300Seeder::class);

        $echo = Product::factory()->create(['sku' => 'AURORA-ECHO', 'name' => 'Aurora ECHO', 'product_type' => 'variable', 'unit' => 'unit', 'weight_grams' => 14000, 'length_cm' => 0, 'width_cm' => 0, 'height_cm' => 0]);
        ProductVariant::create(['product_id' => $echo->id, 'sku' => 'AURORA-ECHO-8', 'name' => 'ECHO-8', 'option_values' => ['Unit' => 'ECHO-8'], 'price' => 31890000, 'weight_grams' => 82500, 'is_active' => true, 'sort_order' => 2]);
        ProductVariant::create(['product_id' => $echo->id, 'sku' => 'AURORA-ECHO-1', 'name' => 'ECHO-1', 'option_values' => ['Unit' => 'ECHO-1'], 'price' => 6960000, 'weight_grams' => 13500, 'length_cm' => 24, 'width_cm' => 19, 'height_cm' => 31, 'is_active' => true, 'sort_order' => 0]);

        $this->seed(VarianBeratDimensiSeeder::class);

        $this->assertSame('230×115×49', $this->dimsOf('PH605-5KWP-15KWH'));
        $this->assertSame('60×50×45', $this->dimsOf('PH605-0KWP-5KWH'));
        $this->assertSame('230×115×15', $this->dimsOf('PAKET-APEX300-B3'));
        $this->assertSame('54×27×84', $this->dimsOf('AURORA-ECHO-8'));
        $this->assertSame(82500, (int) ProductVariant::where('sku', 'AURORA-ECHO-8')->value('weight_grams'));
        // Sudah diisi admin → tidak disentuh.
        $this->assertSame('24×19×31', $this->dimsOf('AURORA-ECHO-1'));
        $this->assertSame(13500, (int) ProductVariant::where('sku', 'AURORA-ECHO-1')->value('weight_grams'));
        // Jumlah kolli paket dilengkapi (hanya bila masih 1).
        $this->assertSame(4, (int) Product::where('sku', 'PAKET-PH605-SOLAR')->value('package_count'));
        $this->assertSame(4, (int) Product::where('sku', 'PAKET-APEX300-SOLAR1200')->value('package_count'));

        // Dijalankan ulang: tidak ada yang berubah.
        $this->seed(VarianBeratDimensiSeeder::class);
        $this->assertSame('230×115×49', $this->dimsOf('PH605-5KWP-15KWH'));
    }

    /** Paket 290 kg terdiri dari 6 kolli → kolli terberat ±48 kg, bukan 290 kg (forklift kargo tidak salah kena). */
    public function test_heaviest_package_of_a_bundle_is_divided_by_its_package_count(): void
    {
        $this->defaultWarehouse();
        $this->seed(PaketAmalSeeder::class);
        $product = Product::where('sku', 'PAKET-AMAL')->firstOrFail();
        $variant = ProductVariant::where('sku', 'PAKET-AMAL-8000')->firstOrFail();

        $item = new CartItem;
        $item->quantity = 1;
        $item->setRelation('product', $product);
        $item->setRelation('variant', $variant);
        $cart = new Cart;
        $cart->setRelation('items', new Collection([$item]));

        $ctx = app(ShippingService::class)->contextFor($cart, 'Jawa Barat', 'Bandung');
        $this->assertSame(290000, $ctx->totalActualGrams);
        $this->assertSame(intdiv(290000, 6), $ctx->maxUnitGrams);
        $this->assertSame(6, $ctx->packageCount);
    }

    private function dimsOf(string $sku): string
    {
        $v = ProductVariant::where('sku', $sku)->firstOrFail();

        return $v->lengthCm().'×'.$v->widthCm().'×'.$v->heightCm();
    }
}
