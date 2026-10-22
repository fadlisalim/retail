<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\CategorySeeder;
use Database\Seeders\UbetterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Katalog UBetter: harga jual 1 pcs, modal tier 100 pcs+, stok stocklist, foto & datasheet. */
class UbetterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_ubetter_catalog_with_variants_prices_costs_and_stock(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $this->seed(CategorySeeder::class);
        $this->seed(UbetterSeeder::class);
        $this->seed(UbetterSeeder::class); // idempotent

        $brand = Brand::where('slug', 'ubetter')->firstOrFail();
        $this->assertSame('brands/ubetter.png', $brand->logo_path);
        $products = Product::where('brand_id', $brand->id)->get();
        $this->assertCount(14, $products);
        $this->assertSame(21, ProductVariant::whereIn('product_id', $products->pluck('id'))->count());

        $v = ProductVariant::where('sku', 'UB-LFP12.8-100')->firstOrFail();
        $this->assertEquals(3_200_000, $v->price);
        $this->assertEquals(2_500_000, $v->cost_price);
        $this->assertSame(52, (int) $v->fresh()->stock);
        $this->assertNotNull($v->image_path);

        $this->assertEquals(29_700_000, ProductVariant::where('sku', 'UB-PW51-200')->value('price'));
        $this->assertEquals(20_600_000, ProductVariant::where('sku', 'UB-PW51-200')->value('cost_price'));
        $this->assertEquals(49_990_000, ProductVariant::where('sku', 'UB-VT48-628')->value('cost_price'));

        $inv = Product::where('sku', 'UB-INV-11K')->firstOrFail();
        $this->assertSame('simple', $inv->product_type);
        $this->assertEquals(16_300_000, $inv->price);
        $this->assertEquals(10_900_000, $inv->cost_price);
        $this->assertSame(5, (int) $inv->stock);
        $this->assertStringContainsString('2 × 5.500 W', $inv->specifications);
        $this->assertSame(1, $inv->documents()->count());
        $this->assertNotNull($inv->main_image_path);
        $this->assertTrue($inv->categories->contains('slug', 'inverter-hybrid'));
        $this->assertNotNull(Product::where('slug', 'inverter-surya-ubetter-6-2-kw')->first());

        $this->assertTrue(Product::where('sku', 'UB-AIO')->value('requires_freight'));
        $this->assertFalse((bool) Product::where('sku', 'UB-LFP12')->value('requires_freight'));

        // Datasheet: powerwall, rack 3U, all-in-one terisi; foto datasheet jadi foto utama & foto varian.
        $pw = Product::where('sku', 'UB-PW51')->firstOrFail();
        $this->assertStringContainsString('600 × 380 × 145 mm', $pw->specifications);
        $this->assertStringContainsString('powerwall-10kwh', $pw->main_image_path);
        $this->assertStringContainsString('powerwall-5kwh', ProductVariant::where('sku', 'UB-PW51-100')->value('image_path'));
        $this->assertStringContainsString('RS485, RS232, CAN', Product::where('sku', 'UB-RK51')->value('specifications'));
        $this->assertStringContainsString('UBT-N2HT11-P15', Product::where('sku', 'UB-AIO')->value('specifications'));
        $this->assertSame(145_000, (int) ProductVariant::where('sku', 'UB-AIO-15-11K')->value('weight_grams'));

        // Inverter 6/12 kW (GPEO) & AIO 3+8 dari datasheet.
        $inv6 = Product::where('sku', 'UB-INV-6K')->firstOrFail();
        $this->assertStringContainsString('GPEO-6KL1', $inv6->name);
        $this->assertStringContainsString('120 A / 130 A', $inv6->specifications);
        $this->assertNotNull($inv6->main_image_path);
        $this->assertStringContainsString('2 MPPT', Product::where('sku', 'UB-INV-12K')->value('specifications'));
        $this->assertStringContainsString('820 × 450 × 230,5 mm', Product::where('sku', 'UB-AIO')->value('specifications'));

        // Jalan ulang memperbarui konten lama (spesifikasi "menyusul" → datasheet).
        $pw->forceFill(['specifications' => '<p>lama</p>', 'main_image_path' => 'products/ubetter/powerwall.webp'])->save();
        $this->seed(UbetterSeeder::class);
        $this->assertStringContainsString('16S2P', $pw->fresh()->specifications);
        $this->assertStringContainsString('powerwall-10kwh', $pw->fresh()->main_image_path);

        // Halaman produk tampil, modal tidak tampil ke publik.
        $page = $this->get(route('products.show', 'baterai-lifepo4-ubetter-12-8v-6-200ah'))->assertOk()->assertSee('12,8V 100Ah')->assertSee('Rp 3.200.000');
        $page->assertDontSee('Rp 2.500.000');
    }
}
