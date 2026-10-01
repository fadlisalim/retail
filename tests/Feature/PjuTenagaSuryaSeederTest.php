<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\PjuTenagaSuryaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lampu PJU Tenaga Surya (Okt 2026): 12 produk ICOM / SOLARI / LEIND / SUNYO
 * dari daftar harga + brosur, dengan varian per daya/paket, foto brosur, dan
 * kategori All-in-One / Two-in-One.
 */
class PjuTenagaSuryaSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedPju(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $parent = Category::factory()->create(['name' => 'PJU Tenaga Surya', 'slug' => 'pju-tenaga-surya']);
        Category::factory()->create(['name' => 'PJU All-in-One', 'slug' => 'pju-tenaga-surya-pju-all-in-one', 'parent_id' => $parent->id]);
        Category::factory()->create(['name' => 'PJU Two-in-One', 'slug' => 'pju-tenaga-surya-pju-two-in-one', 'parent_id' => $parent->id]);
        $this->seed(PjuTenagaSuryaSeeder::class);
    }

    public function test_seeds_twelve_pju_products_with_variants_prices_and_photos(): void
    {
        $this->seedPju();

        $this->assertSame(12, Product::where('sku', 'like', 'ICOM-%')->orWhere('sku', 'like', 'SOLARI-%')->orWhere('sku', 'like', 'LEIND-%')->orWhere('sku', 'like', 'SUNYO-%')->count());
        $this->assertSame(4, Brand::whereIn('slug', ['icom', 'solari', 'leind', 'sunyo'])->count());

        // Harga dari daftar harga.
        $this->assertEquals(1_300_000, ProductVariant::where('sku', 'ICOM-IC-AIOM60')->value('price'));
        $this->assertEquals(1_815_000, ProductVariant::where('sku', 'ICOM-IC-AIOM100')->value('price'));
        $this->assertEquals(4_228_000, ProductVariant::where('sku', 'ICOM-AIO-80-12V')->value('price'));
        $this->assertEquals(3_070_000, ProductVariant::where('sku', 'ICOM-IC-TEEN180')->value('price'));
        $this->assertEquals(2_535_000, ProductVariant::where('sku', 'SOLARI-SL-MW120')->value('price'));
        $this->assertEquals(2_820_000, ProductVariant::where('sku', 'LEIND-LI-RON128')->value('price'));
        $this->assertEquals(5_500_000, ProductVariant::where('sku', 'LEIND-LI-CITY150-12V-200')->value('price'));
        $this->assertEquals(950_000, ProductVariant::where('sku', 'LEIND-LI-ZLW100')->value('price'));
        $this->assertEquals(2_695_000, ProductVariant::where('sku', 'ICOM-IC-YIN80')->value('price'));
        $this->assertEquals(3_295_000, ProductVariant::where('sku', 'ICOM-IC-FIN120')->value('price'));
        $this->assertEquals(3_275_000, ProductVariant::where('sku', 'SUNYO-SY-BEK110')->value('price'));
        $this->assertEquals(1_999_000, Product::where('sku', 'LEIND-LI-SLIM100')->value('price'));
        $this->assertEquals(950_000, Product::where('sku', 'LEIND-LI-VILL100')->value('price'));

        // Induk = varian termurah ("mulai dari"), kategori sesuai jenis, two-in-one = 2 kolli.
        $aiom = Product::where('sku', 'ICOM-IC-AIOM')->firstOrFail();
        $this->assertEquals(1_300_000, $aiom->price);
        $this->assertSame('variable', $aiom->product_type);
        $this->assertSame('pju-tenaga-surya-pju-all-in-one', $aiom->category->slug);
        $this->assertSame(1, (int) $aiom->package_count);
        $this->assertCount(3, $aiom->variants);
        $this->assertSame(10500, ProductVariant::where('sku', 'ICOM-IC-AIOM60')->first()->weightGrams());
        $this->assertTrue($aiom->categories->contains('slug', 'pju-tenaga-surya'));

        $city = Product::where('sku', 'LEIND-LI-CITY')->firstOrFail();
        $this->assertSame('pju-tenaga-surya-pju-two-in-one', $city->category->slug);
        $this->assertSame(2, (int) $city->package_count);
        $this->assertCount(4, $city->variants);

        // Foto brosur terpasang: per varian untuk AIOM/TEEN, foto utama untuk semua.
        $this->assertNotNull(ProductVariant::where('sku', 'ICOM-IC-TEEN90')->value('image_path'));
        foreach (['ICOM-IC-AIOM', 'ICOM-IC-TEEN', 'SOLARI-SL-MW', 'LEIND-LI-RON', 'LEIND-LI-CITY', 'ICOM-IC-YIN', 'SUNYO-SY-BEK', 'LEIND-LI-SLIM100', 'LEIND-LI-VILL100'] as $sku) {
            $p = Product::where('sku', $sku)->firstOrFail();
            $this->assertNotNull($p->main_image_path, $sku.' harus punya foto utama');
            Storage::disk('public')->assertExists($p->main_image_path);
        }
        $this->assertNull(Product::where('sku', 'ICOM-AIO-SENSOR')->value('main_image_path')); // tanpa brosur

        // Stok awal placeholder 5 per varian / produk.
        $this->assertSame(5, (int) ProductVariant::where('sku', 'SUNYO-SY-BEK60')->first()->fresh()->stock);
        $this->assertSame(5, (int) Product::where('sku', 'LEIND-LI-VILL100')->first()->stock);

        // Modal belum diketahui → kosong.
        $this->assertNull($aiom->cost_price);

        // Halaman produk tampil dengan spesifikasi dan catatan cash.
        $this->get(route('products.show', $aiom->slug))->assertOk()->assertSee('IC-AIOM100')->assertSee('transaksi cash');
    }

    public function test_rerun_keeps_admin_edits_and_adds_missing_variants(): void
    {
        $this->seedPju();

        $teen = Product::where('sku', 'ICOM-IC-TEEN')->firstOrFail();
        $teen->update(['name' => 'ICOM TEEN (edit admin)', 'price' => 2_000_000]);
        ProductVariant::where('sku', 'ICOM-IC-TEEN150')->delete();
        $totalBefore = Product::count();

        $this->seed(PjuTenagaSuryaSeeder::class);

        $this->assertSame($totalBefore, Product::count());
        $this->assertSame('ICOM TEEN (edit admin)', $teen->fresh()->name);
        $this->assertEquals(2_000_000, $teen->fresh()->price);
        $this->assertNotNull(ProductVariant::where('sku', 'ICOM-IC-TEEN150')->first());
        $this->assertSame(4, $teen->variants()->count());
    }
}
