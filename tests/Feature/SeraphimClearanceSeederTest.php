<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use App\Services\StockService;
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
        $this->assertSame('used', $p->condition); // bekas proyek, fungsi normal
        $this->assertTrue((bool) $p->is_clearance);
        $this->assertTrue((bool) $p->requires_freight);
        $this->assertSame(23000, (int) $p->weight_grams);
        $this->assertSame('seraphim', $p->brand->slug);
        $this->assertSame('brands/seraphim.png', $p->brand->logo_path);
        Storage::disk('public')->assertExists('brands/seraphim.png');
        $this->get(route('brands.index'))->assertOk()->assertSee('brands/seraphim.png');
        $this->assertSame('panel-surya-monocrystalline', $p->category->slug);
        $this->assertTrue($p->categories->contains('slug', 'panel-surya'));
        $this->assertSame('Garansi toko 3 tahun', $p->warranty);
        $this->assertSame(100, (int) $p->fresh()->stock);

        // Galeri: promo berlogo (utama, tanpa watermark) → foto palet → gambar datasheet.
        $this->assertStringContainsString('promo-srp-345-6ma-dg', $p->main_image_path);
        Storage::disk('public')->assertExists($p->main_image_path);
        $this->assertSame(3, $p->images()->count());
        $this->assertSame(
            ['promo-srp-345-6ma-dg-v2', 'foto-palet-srp-345', 'srp-345-6ma-dg'],
            $p->images()->orderBy('sort_order')->pluck('path')->map(fn ($x) => pathinfo($x, PATHINFO_FILENAME))->all(),
        );

        $doc = $p->documents()->firstOrFail();
        Storage::disk('public')->assertExists($doc->path);
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($doc->path));

        $detail = $p->conditionDetail;
        $this->assertNotNull($detail);
        $this->assertTrue((bool) $detail->is_negotiable);
        $this->assertSame(100, (int) $detail->available_quantity);
        $this->assertEquals(345, $p->attributeValues()->whereHas('attribute', fn ($q) => $q->where('slug', 'spesifikasi-panel-surya-daya-maksimum'))->value('value_number'));

        // Tampil di halaman produk & halaman clearance dengan label kondisi.
        $this->get(route('products.show', $p->slug))->assertOk()
            ->assertSee('SRP-345-6MA-DG')->assertSee('Bekas Pakai')->assertSee('kotoran / bekas pemakaian minor', false)->assertSee('Rp 850.000')->assertSee('JAMINAN TERMURAH')->assertSee('Dokumen (1)')
            ->assertDontSee('marketplace')->assertDontSee('Sisa Proyek');
        $this->get('/barang-clearance')->assertOk()->assertSee('Seraphim 345Wp');

        // Idempotent: editan admin (harga, stok) tidak ditimpa, dokumen tidak digandakan.
        $p->update(['price' => 800_000]);
        $this->seed(SeraphimClearanceSeeder::class);
        $this->assertEquals(800_000, $p->fresh()->price);
        $this->assertSame(1, Product::where('sku', SeraphimClearanceSeeder::SKU)->count());
        $this->assertSame(1, $p->documents()->count());
        $this->assertSame(3, $p->images()->count()); // galeri tidak digandakan
        $this->assertSame(100, (int) $p->fresh()->stock);
    }

    /** Produk versi awal (hanya gambar datasheet) → promo jadi foto utama, foto palet ditambah, datasheet tetap. */
    public function test_rerun_adds_promo_and_pallet_photos_to_an_existing_product(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $this->seed(SeraphimClearanceSeeder::class);
        $p = Product::where('sku', SeraphimClearanceSeeder::SKU)->firstOrFail();
        $p->images()->where(fn ($q) => $q->where('path', 'like', '%promo-%')->orWhere('path', 'like', '%palet%'))->delete();
        $datasheet = $p->images()->firstOrFail();
        Storage::disk('public')->put('products/seraphim/promo-srp-345-6ma-dg.webp', 'old');
        $p->images()->create(['path' => 'products/seraphim/promo-srp-345-6ma-dg.webp', 'alt' => 'promo lama', 'sort_order' => 0]); // versi lama
        $p->forceFill(['main_image_path' => $datasheet->path])->save();

        $this->seed(SeraphimClearanceSeeder::class);

        $p->refresh();
        $this->assertStringContainsString('promo-srp-345-6ma-dg-v2', $p->main_image_path);
        $this->assertSame(3, $p->images()->count());
        $this->assertSame(2, (int) $p->images()->where('path', $datasheet->path)->value('sort_order'));
        $this->assertFalse($p->images()->where('path', 'products/seraphim/promo-srp-345-6ma-dg.webp')->exists()); // promo lama dibuang
        Storage::disk('public')->assertMissing('products/seraphim/promo-srp-345-6ma-dg.webp');
    }

    /** Produk dari versi awal seeder ("Baru - Sisa Proyek", pembanding marketplace) → kondisi Bekas Pakai + teks baru; harga admin tetap. */
    public function test_rerun_fixes_condition_from_new_surplus_to_used_with_minor_dirt(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $this->seed(SeraphimClearanceSeeder::class);
        $p = Product::where('sku', SeraphimClearanceSeeder::SKU)->firstOrFail();
        $p->forceFill([
            'condition' => 'new_project_surplus',
            'name' => 'Panel Surya Seraphim 345Wp Mono Double Glass SRP-345-6MA-DG (Sisa Proyek)',
            'description' => '<p>Kondisi: BARU, sisa proyek PLTS. Dijual <strong>clearance Rp 850.000/panel</strong> — bandingkan dengan panel 350Wp baru di marketplace yang umumnya Rp 1,6–1,9 juta.</p>',
            'short_description' => 'kondisi BARU sisa proyek. Harga clearance Rp 850.000 — jauh di bawah harga pasar panel 350Wp.',
            'badge_text' => 'Clearance',
            'price' => 820_000,
        ])->save();
        $p->conditionDetail->update(['defect_notes' => 'Tidak ada cacat fungsi.', 'available_quantity' => 97]);

        $this->seed(SeraphimClearanceSeeder::class);

        $p->refresh();
        $this->assertSame('used', $p->condition);
        $this->assertStringContainsString('(Bekas Proyek)', $p->name);
        $this->assertStringContainsString('kotoran / bekas pemakaian minor', $p->description);
        $this->assertStringNotContainsString('marketplace', $p->description);
        $this->assertStringNotContainsString('BARU', $p->short_description);
        $this->assertSame('Jaminan Termurah', $p->badge_text);
        $this->assertEquals(820_000, $p->price); // harga editan admin tidak disentuh
        $this->assertStringContainsString('Kotoran / bekas pemakaian minor', $p->conditionDetail->defect_notes);
        $this->assertSame(97, (int) $p->conditionDetail->available_quantity); // jumlah tersedia dipertahankan

        // Sudah 'used' → jalan lagi tidak mengubah apa pun (editan admin aman).
        $p->update(['description' => '<p>Edit admin.</p>']);
        $this->seed(SeraphimClearanceSeeder::class);
        $this->assertSame('<p>Edit admin.</p>', $p->fresh()->description);
    }

    /** Versi awal seeder mengisi stok placeholder 10 → dinaikkan ke 100; tidak disentuh bila sudah ada mutasi lain. */
    public function test_rerun_raises_placeholder_stock_to_one_hundred_unless_stock_was_touched(): void
    {
        Storage::fake('public');
        $this->defaultWarehouse();
        $this->seed(SeraphimClearanceSeeder::class);
        $p = Product::where('sku', SeraphimClearanceSeeder::SKU)->firstOrFail();
        $stock = app(StockService::class);

        // Simulasi versi awal: hanya satu mutasi seeder dengan saldo 10.
        StockMovement::where('product_id', $p->id)->delete();
        WarehouseStock::where('product_id', $p->id)->delete();
        $stock->adjust($p, null, 10, StockMovementType::Purchase, note: 'Stok awal sisa proyek (seeder)');
        $this->assertSame(10, (int) $p->fresh()->stock);

        $this->seed(SeraphimClearanceSeeder::class);
        $this->assertSame(100, (int) $p->fresh()->stock);
        $this->assertSame(100, (int) $p->conditionDetail->fresh()->available_quantity);

        // Sudah ada penjualan/koreksi admin → stok dibiarkan.
        $stock->adjust($p, null, -3, StockMovementType::Sale, note: 'Terjual 3');
        $this->seed(SeraphimClearanceSeeder::class);
        $this->assertSame(97, (int) $p->fresh()->stock);
    }
}
