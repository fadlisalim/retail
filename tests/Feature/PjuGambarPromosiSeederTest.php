<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\PjuGambarPromosiSeeder;
use Database\Seeders\PjuTenagaSuryaSeeder;
use Database\Seeders\PompaHybridLarensSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Gambar promosi 1:1 menggantikan foto brosur sebagai foto utama PJU + pompa LARENS. */
class PjuGambarPromosiSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $parent = Category::factory()->create(['name' => 'PJU Tenaga Surya', 'slug' => 'pju-tenaga-surya']);
        Category::factory()->create(['name' => 'PJU All-in-One', 'slug' => 'pju-tenaga-surya-pju-all-in-one', 'parent_id' => $parent->id]);
        Category::factory()->create(['name' => 'PJU Two-in-One', 'slug' => 'pju-tenaga-surya-pju-two-in-one', 'parent_id' => $parent->id]);
        $pompa = Category::factory()->create(['name' => 'Pompa Air Tenaga Surya', 'slug' => 'pompa-air-tenaga-surya']);
        Category::factory()->create(['name' => 'Submersible', 'slug' => 'pompa-air-tenaga-surya-submersible', 'parent_id' => $pompa->id]);
        $this->seed(PjuTenagaSuryaSeeder::class);
        $this->seed(PompaHybridLarensSeeder::class);
    }

    public function test_promo_images_become_main_photo_and_variant_thumbnails(): void
    {
        $this->seedAll();
        $ron = Product::where('sku', 'LEIND-LI-RON')->firstOrFail();
        $oldMain = $ron->main_image_path;
        $this->assertStringContainsString('li-ron', $oldMain);

        $this->seed(PjuGambarPromosiSeeder::class);

        // Foto utama = gambar promo (tersimpan, dioptimalkan ke webp).
        foreach (['ICOM-IC-FIN' => 'promo-ic-fin100', 'ICOM-IC-YIN' => 'promo-ic-yin40', 'SOLARI-SL-MW' => 'promo-sl-mw80', 'LEIND-LI-VILL100' => 'promo-li-vill100',
            'SUNYO-SY-BEK' => 'promo-sy-bek', 'LEIND-LI-SLIM100' => 'promo-li-slim', 'LEIND-LI-CITY' => 'promo-li-city', 'LEIND-LI-RON' => 'promo-li-ron',
            'LEIND-LI-ZLW' => 'promo-li-zlw', 'LARENS-PSS-HYBRID' => 'promo-larens-pompa'] as $sku => $stem) {
            $p = Product::where('sku', $sku)->firstOrFail();
            $this->assertStringContainsString($stem, $p->main_image_path, $sku);
            Storage::disk('public')->assertExists($p->main_image_path);
            $this->assertSame($p->main_image_path, $p->images()->orderBy('sort_order')->first()->path, "{$sku}: promo harus foto pertama galeri");
        }
        $this->assertStringEndsWith('.webp', Product::where('sku', 'LEIND-LI-RON')->value('main_image_path'));

        // Varian per daya dapat thumbnail promo masing-masing; varian tanpa gambar khusus pakai promo produk.
        $this->assertStringContainsString('promo-ic-yin60', ProductVariant::where('sku', 'ICOM-IC-YIN60')->value('image_path'));
        $this->assertStringContainsString('promo-sl-mw120', ProductVariant::where('sku', 'SOLARI-SL-MW120')->value('image_path'));
        $this->assertStringContainsString('promo-li-city', ProductVariant::where('sku', 'LEIND-LI-CITY150-160')->value('image_path'));

        // Foto brosur yang gelap dibuang; halaman brosur informatif tetap sebagai foto kedua.
        $ron->refresh();
        $this->assertFalse($ron->images()->where('path', $oldMain)->exists());
        $this->assertSame(1, $ron->images()->count());
        $city = Product::where('sku', 'LEIND-LI-CITY')->firstOrFail();
        $this->assertSame(2, $city->images()->count());
        $this->assertStringContainsString('li-city', $city->images()->orderBy('sort_order')->skip(1)->first()->path);
        $larens = Product::where('sku', 'LARENS-PSS-HYBRID')->firstOrFail();
        $this->assertSame(1, $larens->images()->count());
        $this->assertSame(1, $larens->documents()->count()); // brosur PDF tetap

        // AIOM: promo per varian; TEEN: hanya 90W yang punya promo, varian lain tetap foto brosur.
        $this->assertStringContainsString('promo-ic-aiom60', Product::where('sku', 'ICOM-IC-AIOM')->value('main_image_path'));
        $this->assertStringContainsString('promo-ic-aiom100', ProductVariant::where('sku', 'ICOM-IC-AIOM100')->value('image_path'));
        $this->assertStringContainsString('promo-ic-teen90', Product::where('sku', 'ICOM-IC-TEEN')->value('main_image_path'));
        $this->assertStringContainsString('promo-ic-teen90', ProductVariant::where('sku', 'ICOM-IC-TEEN90')->value('image_path'));
        $this->assertStringContainsString('ic-teen150', ProductVariant::where('sku', 'ICOM-IC-TEEN150')->value('image_path'));
        $this->assertSame(6, Product::where('sku', 'ICOM-IC-AIOM')->firstOrFail()->images()->count()); // 3 promo + 3 brosur
        $this->assertNull(Product::where('sku', 'ICOM-AIO-SENSOR')->value('main_image_path')); // tanpa gambar promo → tidak disentuh

        $this->get(route('products.show', $ron->slug))->assertOk()->assertSee('promo-li-ron');

        // Idempotent: jalankan ulang → tidak ada duplikat, foto utama tetap.
        $this->seed(PjuGambarPromosiSeeder::class);
        $this->assertSame(1, $ron->images()->count());
        $this->assertSame(2, $city->images()->count());
        $this->assertSame(1, Product::where('sku', 'ICOM-IC-YIN')->firstOrFail()->images()->where('path', 'like', '%promo-ic-yin60%')->count());
    }
}
