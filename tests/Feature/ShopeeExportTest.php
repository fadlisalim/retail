<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\Shopee\MassUploadExporter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use ZipArchive;

/**
 * Ekspor katalog ke template Mass Upload Shopee: satu baris per produk /
 * varian, teks polos, link foto publik, berat gram, aturan batas Shopee.
 */
class ShopeeExportTest extends TestCase
{
    use RefreshDatabase;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();
        $this->out = sys_get_temp_dir().'/shopee-test-'.uniqid().'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->out);
        parent::tearDown();
    }

    /** @return array<int, array<string, string>> baris => [kolom => nilai] dari sheet Template */
    private function readRows(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet2.xml'));
        $zip->close();
        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($xml->xpath('//m:sheetData/m:row') as $row) {
            $r = (int) $row['r'];
            if ($r < 7) {
                continue;
            }
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $col = array_search($m[1], array_map(fn ($i) => $this->letter($i), range(1, count(MassUploadExporter::COLUMNS))), true);
                $key = MassUploadExporter::COLUMNS[$col];
                $rows[$r][$key] = (string) $c['t'] === 'inlineStr' ? (string) $c->is->t : (string) $c->v;
            }
        }

        return $rows;
    }

    private function letter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + $n % 26).$s;
            $n = intdiv($n, 26);
        }

        return $s;
    }

    public function test_simple_and_variable_products_are_written_into_the_shopee_template(): void
    {
        $this->defaultWarehouse();
        $brand = Brand::factory()->create(['name' => 'Jinko']);
        $category = Category::factory()->create(['name' => 'Baterai', 'slug' => 'baterai']);

        $panel = $this->stockedProduct(12, [
            'sku' => 'JINKO-580', 'name' => 'Solar Panel 580 Wp N-type', 'brand_id' => $brand->id,
            'price' => 1_900_000, 'sale_price' => null, 'weight_grams' => 27000, 'length_cm' => 227.8, 'width_cm' => 113.4, 'height_cm' => 3,
            'main_image_path' => 'produk/jinko.jpg', 'min_purchase' => 2, 'warranty' => 'Garansi 12 tahun',
            'description' => '<p>Panel <strong>TOPCon</strong> efisiensi tinggi.</p><ul><li>Bifacial</li><li>1500 V</li></ul>',
            'specifications' => '<table><tr><th>Daya</th><td>580 Wp</td></tr><tr><th>Efisiensi</th><td>22,45 %</td></tr></table>',
        ]);
        $panel->images()->create(['path' => 'produk/jinko-2.jpg', 'sort_order' => 1]);
        $panel->images()->create(['path' => 'produk/jinko-poster.jpg', 'video_path' => 'produk/jinko.mp4', 'sort_order' => 2]);

        $paket = Product::factory()->create([
            'sku' => 'PAKET-AMAL', 'name' => 'Paket PLTS Amal', 'product_type' => 'variable', 'category_id' => $category->id, 'brand_id' => null,
            'price' => 24_900_000, 'weight_grams' => 100000, 'main_image_path' => 'produk/amal.jpg', 'requires_freight' => true,
            'description' => '<p>Paket komplit anti mati lampu untuk rumah.</p>',
        ]);
        foreach ([['AMAL 2000', 24_900_000, 100000, 5], ['AMAL 8000', 54_900_000, 290000, 0]] as $i => [$name, $price, $weight, $stock]) {
            ProductVariant::create([
                'product_id' => $paket->id, 'sku' => 'PAKET-AMAL-'.($i + 1), 'name' => $name, 'option_values' => ['Paket' => $name],
                'price' => $price, 'weight_grams' => $weight, 'length_cm' => 230, 'width_cm' => 115, 'height_cm' => 12 + $i, 'stock' => $stock,
                'is_active' => true, 'sort_order' => $i,
            ]);
        }

        // Dilewati: hanya penawaran, ambil di lokasi, draft. Tanpa foto sampul tetap ikut (dilengkapi di Shopee).
        Product::factory()->create(['sku' => 'NOIMG', 'name' => 'Tanpa Foto Sampul', 'main_image_path' => null, 'weight_grams' => 700]);
        Product::factory()->create(['sku' => 'QUOTE', 'name' => 'Hanya Penawaran', 'main_image_path' => 'p/q.jpg', 'requires_quotation' => true]);
        Product::factory()->create(['sku' => 'PICKUP', 'name' => 'Ambil Sendiri', 'main_image_path' => 'p/p.jpg', 'pickup_only' => true]);
        Product::factory()->create(['sku' => 'DRAFT', 'name' => 'Draft', 'main_image_path' => 'p/d.jpg', 'status' => 'draft']);

        $exporter = app(MassUploadExporter::class);
        $this->assertSame(4, $exporter->write($this->out));
        $rows = $this->readRows($this->out);

        $this->assertSame([7, 8, 9, 10], array_keys($rows));
        $this->assertSame('NOIMG', $rows[10]['ps_sku_parent_short']);
        $this->assertArrayNotHasKey('ps_item_cover_image', $rows[10]);
        $this->assertStringContainsString('NOIMG: belum punya foto sampul', implode("\n", $exporter->warnings));
        $p = $rows[7];
        $this->assertSame('Jinko Solar Panel 580 Wp N-type', $p['ps_product_name']);
        $this->assertStringContainsString("Panel TOPCon efisiensi tinggi.\n• Bifacial\n• 1500 V", $p['ps_product_description']);
        $this->assertStringContainsString("SPESIFIKASI\nDaya: 580 Wp\nEfisiensi: 22,45 %", $p['ps_product_description']);
        $this->assertStringContainsString('Garansi: Garansi 12 tahun', $p['ps_product_description']);
        $this->assertStringNotContainsString('<', $p['ps_product_description']);
        $this->assertSame('2', $p['ps_minimum_purchase_quantity']);
        $this->assertSame('JINKO-580', $p['ps_sku_parent_short']);
        $this->assertSame('JINKO-580', $p['ps_sku_short']);
        $this->assertSame('No (ID)', $p['ps_dangerous_goods']);
        $this->assertSame('1900000', $p['ps_price']);
        $this->assertSame('12', $p['ps_stock']);
        $this->assertSame('27000', $p['ps_weight']);
        $this->assertSame(['227.8', '113.4', '3'], [$p['ps_length'], $p['ps_width'], $p['ps_height']]);
        $this->assertSame(url('storage/produk/jinko.jpg'), $p['ps_item_cover_image']);
        $this->assertSame(url('storage/produk/jinko-2.jpg'), $p['ps_item_image_1']);
        $this->assertArrayNotHasKey('ps_item_image_2', $p); // video tidak ikut
        $this->assertSame('Aktif', $p['channel_id.8003']);
        $this->assertSame('Aktif', $p['channel_id.8005']);
        $this->assertArrayNotHasKey('et_title_variation_1', $p);
        $this->assertArrayNotHasKey('ps_category', $p);

        $v1 = $rows[8];
        $v2 = $rows[9];
        $this->assertSame('100043', $v1['ps_category']); // kategori baterai dipetakan
        $this->assertSame('Yes (ID)', $v1['ps_dangerous_goods']);
        $this->assertSame('PAKET-AMAL', $v1['et_title_variation_integration_no']);
        $this->assertSame('PAKET-AMAL', $v2['et_title_variation_integration_no']);
        $this->assertSame('Paket', $v1['et_title_variation_1']);
        $this->assertSame(['AMAL 2000', 'AMAL 8000'], [$v1['et_title_option_for_variation_1'], $v2['et_title_option_for_variation_1']]);
        $this->assertSame(['PAKET-AMAL-1', 'PAKET-AMAL-2'], [$v1['ps_sku_short'], $v2['ps_sku_short']]);
        $this->assertSame(['24900000', '54900000'], [$v1['ps_price'], $v2['ps_price']]);
        $this->assertSame(['5', '0'], [$v1['ps_stock'], $v2['ps_stock']]);
        $this->assertSame(['100000', '290000'], [$v1['ps_weight'], $v2['ps_weight']]);
        $this->assertSame(['12', '13'], [$v1['ps_height'], $v2['ps_height']]);
        $this->assertSame('Nonaktif', $v2['channel_id.8003']); // > 50 kg: reguler nonaktif
        $this->assertSame('Aktif', $v2['channel_id.8005']);
        $this->assertSame('Paket PLTS Amal', $v2['ps_product_name']); // nama diulang tiap baris varian
        $this->assertArrayNotHasKey('et_title_image_per_variation', $v1); // tidak semua varian punya foto

        $this->assertCount(2, $exporter->skipped);
        $this->assertStringContainsString('QUOTE', implode("\n", $exporter->skipped));
        $this->assertStringContainsString('PICKUP', implode("\n", $exporter->skipped));
    }

    public function test_shopee_limits_are_flagged_and_texts_are_trimmed(): void
    {
        $this->defaultWarehouse();
        $echo = Product::factory()->create([
            'sku' => 'AURORA-ECHO', 'name' => 'Aurora ECHO', 'product_type' => 'variable', 'price' => 6_960_000,
            'weight_grams' => 14000, 'main_image_path' => 'p/echo.jpg', 'description' => str_repeat('Deskripsi panjang sekali. ', 200),
        ]);
        foreach ([['ECHO-1 · 500W / 1 kWh', 6_960_000], ['ECHO-16 · 10.000W / 16 kWh', 66_600_000]] as $i => [$name, $price]) {
            ProductVariant::create([
                'product_id' => $echo->id, 'sku' => 'AURORA-ECHO-'.$i, 'name' => $name, 'option_values' => ['Baterai Ekspansi Unit' => $name],
                'price' => $price, 'weight_grams' => 14000, 'is_active' => true, 'sort_order' => $i,
            ]);
        }
        Product::factory()->create(['sku' => 'MAHAL', 'name' => 'Inverter 3 Fasa 60 kW', 'price' => 160_500_000, 'weight_grams' => 90000, 'main_image_path' => 'p/m.jpg']);

        $exporter = app(MassUploadExporter::class);
        $this->assertSame(3, $exporter->write($this->out));
        $rows = $this->readRows($this->out);

        $this->assertSame('Baterai Ekspan', $rows[7]['et_title_variation_1']); // ≤ 14 karakter
        $this->assertSame(['ECHO-1', 'ECHO-16'], [$rows[7]['et_title_option_for_variation_1'], $rows[8]['et_title_option_for_variation_1']]); // bagian sebelum " · "
        $this->assertSame(3000, mb_strlen($rows[7]['ps_product_description']));

        $warnings = implode("\n", $exporter->warnings);
        $this->assertStringContainsString('AURORA-ECHO: rasio harga varian termahal/termurah 9,6×', $warnings);
        $this->assertStringContainsString('MAHAL: harga di luar batas Shopee', $warnings);
        $this->assertStringContainsString('deskripsi dipotong', $warnings);
        $this->assertStringContainsString('nama variasi "Baterai Ekspansi Unit" dipotong', $warnings);
    }

    public function test_command_and_admin_download(): void
    {
        $this->defaultWarehouse();
        $this->stockedProduct(3, ['sku' => 'X1', 'name' => 'Produk Uji Ekspor', 'main_image_path' => 'p/x.jpg', 'weight_grams' => 500]);

        $this->assertSame(0, Artisan::call('shopee:export', ['--out' => $this->out]));
        $this->assertStringContainsString('1 baris ditulis', Artisan::output());
        $this->assertFileExists($this->out);

        $this->seed(RoleSeeder::class);
        $katalog = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $katalog->roles()->attach(Role::where('slug', 'admin-katalog')->first());
        $this->actingAs($katalog)->get(route('admin.products.index'))->assertOk()->assertSee('Ekspor Shopee');
        $response = $this->actingAs($katalog)->get(route('admin.products.export-shopee'));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('shopee-mass-upload-', $response->headers->get('content-disposition'));

        $sales = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $sales->roles()->attach(Role::where('slug', 'admin-sales')->first());
        $this->actingAs($sales)->get(route('admin.products.export-shopee'))->assertForbidden();
    }
}
