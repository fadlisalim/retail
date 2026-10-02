<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\SeraphimClearanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Clearance sisa proyek: Panel Surya Seraphim 345Wp (Okt 2026). */
class SeraphimClearanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_clearance_panel_with_photo_datasheet_and_condition_detail(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $parent = Category::factory()->create(['name' => 'Panel Surya', 'slug' => 'panel-surya']);
        Category::factory()->create(['name' => 'Monocrystalline', 'slug' => 'panel-surya-monocrystalline', 'parent_id' => $parent->id]);
        $this->seed(AttributeSeeder::class);

        $this->seed(SeraphimClearanceSeeder::class);

        $p = Product::where('sku', SeraphimClearanceSeeder::SKU)->firstOrFail();
        $this->assertEquals(850_000, $p->price);
        $this->assertEquals(450_000, $p->cost_price);
        $this->assertSame('new_project_surplus', $p->condition);
        $this->assertTrue((bool) $p->is_clearance);
        $this->assertTrue((bool) $p->requires_freight);
        $this->assertSame(23000, (int) $p->weight_grams);
        $this->assertSame('seraphim', $p->brand->slug);
        $this->assertSame('panel-surya-monocrystalline', $p->category->slug);
        $this->assertTrue($p->categories->contains('slug', 'panel-surya'));
        $this->assertSame('Garansi toko 3 tahun', $p->warranty);
        $this->assertSame(10, (int) $p->fresh()->stock);

        $this->assertNotNull($p->main_image_path);
        Storage::disk('public')->assertExists($p->main_image_path);
        $this->assertSame(1, $p->images()->count());

        $doc = $p->documents()->firstOrFail();
        Storage::disk('public')->assertExists($doc->path);
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($doc->path));

        $detail = $p->conditionDetail;
        $this->assertNotNull($detail);
        $this->assertTrue((bool) $detail->is_negotiable);
        $this->assertSame(10, (int) $detail->available_quantity);
        $this->assertEquals(345, $p->attributeValues()->whereHas('attribute', fn ($q) => $q->where('slug', 'spesifikasi-panel-surya-daya-maksimum'))->value('value_number'));

        // Tampil di halaman produk & halaman clearance dengan label kondisi.
        $this->get(route('products.show', $p->slug))->assertOk()
            ->assertSee('SRP-345-6MA-DG')->assertSee('Baru - Sisa Proyek')->assertSee('Rp 850.000')->assertSee('JAMINAN TERMURAH')->assertSee('Dokumen (1)')
            ->assertDontSee('marketplace');
        $this->get('/barang-clearance')->assertOk()->assertSee('Seraphim 345Wp');

        // Idempotent: editan admin (harga, stok) tidak ditimpa, dokumen tidak digandakan.
        $p->update(['price' => 800_000]);
        $this->seed(SeraphimClearanceSeeder::class);
        $this->assertEquals(800_000, $p->fresh()->price);
        $this->assertSame(1, Product::where('sku', SeraphimClearanceSeeder::SKU)->count());
        $this->assertSame(1, $p->documents()->count());
        $this->assertSame(10, (int) $p->fresh()->stock);
    }

    /** Produk dari versi awal seeder (ada kalimat pembanding harga marketplace) → teks diganti "JAMINAN TERMURAH". */
    public function test_rerun_replaces_marketplace_comparison_with_jaminan_termurah(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $this->seed(SeraphimClearanceSeeder::class);
        $p = Product::where('sku', SeraphimClearanceSeeder::SKU)->firstOrFail();
        $p->forceFill([
            'description' => '<p>Dijual <strong>clearance Rp 850.000/panel</strong> — bandingkan dengan panel 350Wp baru di marketplace yang umumnya Rp 1,6–1,9 juta. Garansi toko 3 tahun.</p>',
            'short_description' => 'Harga clearance Rp 850.000 — jauh di bawah harga pasar panel 350Wp.',
            'badge_text' => 'Clearance',
        ])->save();

        $this->seed(SeraphimClearanceSeeder::class);

        $p->refresh();
        $this->assertSame('<p>Dijual <strong>clearance Rp 850.000/panel — JAMINAN TERMURAH</strong>. Garansi toko 3 tahun.</p>', $p->description);
        $this->assertStringContainsString('JAMINAN TERMURAH', $p->short_description);
        $this->assertStringNotContainsString('marketplace', $p->short_description);
        $this->assertSame('Jaminan Termurah', $p->badge_text);
    }
}
