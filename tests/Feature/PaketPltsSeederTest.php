<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\PaketPltsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Katalog Paket PLTS (Okt 2026): 46 paket CSV → 18 produk bervarian (SIAGA, Hybrid On-Grid, Hybrid Off-Grid). */
class PaketPltsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedPaket(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $parent = Category::factory()->create(['name' => 'Paket PLTS', 'slug' => 'paket-plts']);
        foreach (['paket-plts-off-grid', 'paket-plts-hybrid', 'paket-plts-rumah', 'paket-plts-kantor'] as $slug) {
            Category::factory()->create(['name' => $slug, 'slug' => $slug, 'parent_id' => $parent->id]);
        }
        $this->seed(PaketPltsSeeder::class);
    }

    public function test_seeds_eighteen_variable_products_with_46_variants_priced_from_csv(): void
    {
        $this->seedPaket();

        $products = Product::where('sku', 'SIAGA')->orWhere('sku', 'like', 'HNG-%')->orWhere('sku', 'like', 'HFG-%')->get();
        $this->assertCount(18, $products);
        $this->assertSame(46, ProductVariant::whereIn('product_id', $products->pluck('id'))->count());
        $this->assertTrue($products->every(fn ($p) => $p->product_type === 'variable' && $p->requires_freight && $p->status === 'published'));

        // SIAGA: varian = kapasitas; harga induk = varian termurah; modal dari CSV.
        $siaga = Product::where('sku', 'SIAGA')->firstOrFail();
        $this->assertCount(4, $siaga->variants);
        $this->assertEquals(18_600_000, $siaga->price);
        $this->assertEquals(12_058_167, $siaga->cost_price);
        $this->assertSame('paket-plts-off-grid', $siaga->category->slug);
        $this->assertTrue($siaga->categories->contains('slug', 'paket-plts-rumah'));
        $v = ProductVariant::where('sku', 'SIAGA-2000')->firstOrFail();
        $this->assertEquals(50_400_000, $v->price);
        $this->assertEquals(32_758_167, $v->cost_price);
        $this->assertSame('Kapasitas', array_key_first($v->option_values));
        $this->assertStringContainsString('2 kWp', $v->name);
        $this->assertSame(3, (int) $v->fresh()->stock);

        // Gambar promo SIAGA: foto per varian + foto utama + galeri 4 foto; produk lain belum ada foto.
        $this->assertStringContainsString('siaga-2000', $v->image_path);
        Storage::disk('public')->assertExists($v->image_path);
        $this->assertStringContainsString('siaga-500', $siaga->main_image_path);
        $this->assertSame(4, $siaga->images()->count());
        // Hybrid On-Grid: foto kelompok (watermark) jadi foto utama semua produk HNG; Off-Grid belum ada foto.
        $this->assertStringContainsString('group-hng', Product::where('sku', 'HNG-1P-3K')->value('main_image_path'));
        $this->assertStringContainsString('group-hng-3p', Product::where('sku', 'HNG-3P-30K')->value('main_image_path')); // per fasa menang
        $this->assertStringNotContainsString('3p', Product::where('sku', 'HNG-1P-3K')->value('main_image_path'));
        $this->assertSame(1, Product::where('sku', 'HNG-1P-3K')->first()->images()->count());
        $this->assertSame(1, Product::where('sku', 'HNG-3P-30K')->first()->images()->count());
        $this->assertNull(Product::where('sku', 'HFG-1P-5')->value('main_image_path'));

        // Hybrid On-Grid: varian Standard/Premium; nama produk tanpa baterai (beda per level).
        $hng = Product::where('sku', 'HNG-1P-8K')->firstOrFail();
        $this->assertSame('Paket PLTS Hybrid On-Grid 1 Fasa 10 kWp · Inverter 8 kW (Standard / Premium)', $hng->name);
        $this->assertSame('paket-plts-hybrid', $hng->category->slug);
        $this->assertEquals(173_100_000, $hng->price);
        $this->assertEquals(197_600_000, ProductVariant::where('sku', 'HNG-1P-8K-P')->value('price'));
        $this->assertStringContainsString('Baterai 23,4 kWh', ProductVariant::where('sku', 'HNG-1P-8K-P')->value('name'));
        // Premium lebih berat (baterai lebih besar) → varian tidak kembar.
        $this->assertGreaterThan(ProductVariant::where('sku', 'HNG-1P-8K-S')->value('weight_grams'), ProductVariant::where('sku', 'HNG-1P-8K-P')->value('weight_grams'));

        // Hybrid Off-Grid: inverter beda per level → rentang di nama produk, detail di nama varian.
        $hfg = Product::where('sku', 'HFG-1P-5')->firstOrFail();
        $this->assertStringContainsString('Inverter 3,6–6,2 kW', $hfg->name);
        $this->assertCount(3, $hfg->variants);
        $this->assertStringContainsString('Ekonomis · Inverter 6,2 kW', ProductVariant::where('sku', 'HFG-1P-5-E')->value('name'));
        $this->assertTrue($hfg->categories->contains('slug', 'paket-plts-off-grid'));
        $this->assertTrue(Product::where('sku', 'HFG-3P-30K')->first()->categories->contains('slug', 'paket-plts-kantor'));

        // Deskripsi memuat tabel varian + opsi tambahan; spesifikasi memuat rincian isi paket.
        $this->assertStringContainsString('NIDI/SLO +Rp 4.000.000', $hng->description);
        $this->assertStringContainsString('garansi baterai 10 th +Rp 11.400.000', $hng->description);
        $this->assertStringContainsString('Rincian isi paket', $hng->specifications);
        $this->assertStringContainsString('Kabel BC 16 mm²', $hng->specifications);
        $this->assertStringContainsString('Kabel PV 4 mm²', $hng->specifications);

        // Halaman produk tampil dengan pilihan varian dan harga.
        $this->get(route('products.show', $hfg->slug))->assertOk()->assertSee('Ekonomis')->assertSee('Premium')->assertSee('Rp 56.600.000')->assertSee('belum termasuk jasa pemasangan');
    }

    public function test_rerun_keeps_admin_edits_and_restores_missing_variants(): void
    {
        $this->seedPaket();
        $siaga = Product::where('sku', 'SIAGA')->firstOrFail();
        $siaga->update(['price' => 17_000_000, 'name' => 'Paket SIAGA (edit)']);
        ProductVariant::where('sku', 'SIAGA-1500')->delete();
        $before = Product::count();

        $this->seed(PaketPltsSeeder::class);

        $this->assertSame($before, Product::count());
        $this->assertEquals(17_000_000, $siaga->fresh()->price);
        $this->assertSame('Paket SIAGA (edit)', $siaga->fresh()->name);
        $this->assertNotNull(ProductVariant::where('sku', 'SIAGA-1500')->first());
        $this->assertSame(4, $siaga->variants()->count());
        $this->assertSame(4, $siaga->images()->count()); // galeri tidak digandakan
        // Produk 3 fasa yang sebelumnya memakai foto kelompok umum → diganti foto per fasa saat seeder dijalankan ulang.
        $p3 = Product::where('sku', 'HNG-3P-10K')->firstOrFail();
        $p3->images()->delete();
        $p3->images()->create(['path' => 'products/paket-plts/group-hng.webp', 'alt' => 'lama', 'sort_order' => 0]);
        $p3->forceFill(['main_image_path' => 'products/paket-plts/group-hng.webp'])->save();
        $this->seed(PaketPltsSeeder::class);
        $this->assertStringContainsString('group-hng-3p', $p3->fresh()->main_image_path);
        $this->assertSame(1, $p3->images()->count());
        $this->assertStringContainsString('siaga-1500', ProductVariant::where('sku', 'SIAGA-1500')->value('image_path'));
    }
}
