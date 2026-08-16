<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Database\Seeders\JsdSolarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The JSD Solar catalogue: seeded once, re-runnable, and browsable. */
class JsdSolarSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->seed(JsdSolarSeeder::class);
    }

    private function products()
    {
        return Product::whereHas('brand', fn ($q) => $q->where('slug', 'jsd-solar'))->get();
    }

    public function test_it_seeds_the_whole_ready_stock_line_up(): void
    {
        $products = $this->products();

        $this->assertCount(18, $products);
        $this->assertCount(18, $products->pluck('sku')->unique());
        $this->assertCount(18, $products->pluck('slug')->unique());

        foreach ($products as $product) {
            $this->assertSame('published', $product->status, $product->sku.' harus terbit');
            $this->assertGreaterThan(0, $product->price, $product->sku.' harus punya harga');
            // Ongkir dihitung dari berat, jadi tidak boleh ada yang kosong.
            $this->assertGreaterThan(0, $product->weight_grams, $product->sku.' harus punya berat');
            $this->assertNotEmpty($product->warranty, $product->sku.' harus punya garansi');
            $this->assertNotNull($product->category_id, $product->sku.' harus punya kategori');
        }
    }

    /** Harga jual = modal ÷ 0,8, dibulatkan ke ATAS — margin tidak boleh < 20%. */
    public function test_every_price_keeps_at_least_twenty_percent_margin(): void
    {
        $cost = [
            'JSD-J1200HC' => 2_450_000, 'JSD-J2500HC' => 2_975_000, 'JSD-J4000E' => 4_950_000,
            'JSD-J6500HC' => 5_850_000, 'JSD-J6200HP' => 6_750_000, 'JSD-J11100HPC' => 9_750_000,
            'JSD-WIFI-PLUG-PRO' => 450_000, 'JSD-XHP12K15' => 8_350_000, 'JSD-XHP4K30' => 14_250_000,
            'JSD-JHP5000' => 22_500_000, 'JSD-XHP65K60' => 23_000_000, 'JSD-J12100' => 3_050_000,
            'JSD-J12200' => 5_950_000, 'JSD-J24100' => 5_950_000, 'JSD-BG48100' => 12_000_000,
            'JSD-J4U48100' => 12_000_000, 'JSD-LFP48200' => 23_000_000, 'JSD-LD48314' => 27_650_000,
        ];

        foreach ($this->products() as $product) {
            $modal = $cost[$product->sku] ?? null;
            $this->assertNotNull($modal, 'Harga modal acuan untuk '.$product->sku.' belum terdaftar di test.');
            $this->assertGreaterThanOrEqual($modal / 0.8, (float) $product->price, $product->sku.' di bawah margin 20%');
        }
    }

    /**
     * Ketiga baterai memakai manual dengan tata letak identik, jadi angka
     * gampang tersalin ke model yang salah — terutama Bluetooth yang HANYA
     * ada di J12100.
     */
    public function test_the_batteries_carry_their_own_datasheet_figures(): void
    {
        $specs = fn (string $sku) => Product::where('sku', $sku)->firstOrFail()->specifications;

        $this->assertStringContainsString('1.280 Wh', $specs('JSD-J12100'));
        $this->assertStringContainsString('14,4 V ±0,2 V', $specs('JSD-J12100'));
        $this->assertStringContainsString('Bluetooth (aplikasi', $specs('JSD-J12100'));

        $this->assertStringContainsString('200 A', $specs('JSD-J12200'));
        $this->assertStringContainsString('Tidak tersedia pada model ini', $specs('JSD-J12200'));

        // 24V: tegangan pengisiannya dua kali lipat model 12V.
        $this->assertStringContainsString('28,8 V ±0,2 V', $specs('JSD-J24100'));
        $this->assertStringContainsString('Tidak tersedia pada model ini', $specs('JSD-J24100'));
    }

    /** Kedua all-in-one bermuatan angka brosur, dan kapasitas baterainya tidak tertukar. */
    public function test_the_all_in_one_units_carry_their_own_battery_capacity(): void
    {
        $specs = fn (string $sku) => Product::where('sku', $sku)->firstOrFail()->specifications;

        $this->assertStringContainsString('5.120 Wh', $specs('JSD-JHP5000'));
        $this->assertStringContainsString('Layar sentuh', $specs('JSD-JHP5000'));
        $this->assertStringContainsString('80 A / 60 A', $specs('JSD-JHP5000'));

        $this->assertStringContainsString('6.144 Wh', $specs('JSD-XHP65K60'));
        $this->assertStringContainsString('9.000 W', $specs('JSD-XHP65K60'));
        $this->assertStringContainsString('53,5 kg', $specs('JSD-XHP65K60'));

        // XHP4K30 satu-satunya AIO yang masih menunggu brosur.
        $this->assertStringContainsString('Menyusul dari pabrikan', $specs('JSD-XHP4K30'));
    }

    /** Baris penampung diganti saat brosur datang — tapi suntingan admin aman. */
    public function test_placeholder_rows_are_upgraded_but_admin_edits_are_kept(): void
    {
        $placeholder = Product::where('sku', 'JSD-J12100')->firstOrFail();
        $placeholder->forceFill(['specifications' => '<p>Menyusul dari pabrikan</p>'])->save();

        $edited = Product::where('sku', 'JSD-J12200')->firstOrFail();
        $edited->forceFill(['specifications' => '<p>Ditulis ulang oleh admin</p>'])->save();

        $this->seed(JsdSolarSeeder::class);

        $this->assertStringContainsString('1.280 Wh', $placeholder->refresh()->specifications);
        $this->assertSame('<p>Ditulis ulang oleh admin</p>', $edited->refresh()->specifications);
    }

    /**
     * Price list menandai tiga model "INCLUDING WIFI". J6200HP tidak termasuk —
     * WiFi-nya dijual terpisah sebagai WiFi Plug Pro, jadi jangan sampai
     * tertukar dan membuat pembeli merasa dijanjikan yang tidak ada.
     */
    public function test_the_models_sold_with_wifi_say_so_and_the_others_do_not(): void
    {
        foreach (['JSD-J4000E', 'JSD-J6500HC', 'JSD-J11100HPC'] as $sku) {
            $product = Product::where('sku', $sku)->firstOrFail();
            $this->assertStringContainsString('WiFi', $product->name, $sku.' harus menyebut WiFi di nama');
            $this->assertStringContainsString('WiFi', $product->specifications, $sku.' harus menyebut WiFi di spesifikasi');
        }

        $j6200 = Product::where('sku', 'JSD-J6200HP')->firstOrFail();
        $this->assertStringNotContainsString('WiFi', $j6200->name);
        $this->assertStringContainsString('opsional', $j6200->description);
    }

    /** Fitur yang wajib tercantum dipulihkan bila baris lama kehilangannya. */
    public function test_a_row_that_lost_a_required_feature_is_refreshed(): void
    {
        $product = Product::where('sku', 'JSD-J11100HPC')->firstOrFail();
        $product->forceFill([
            'name' => 'Inverter Off-Grid JSD Solar J11100HPC 11kW 48V',
            'specifications' => '<table><tbody><tr><th>Model</th><td>J11100HPC</td></tr></tbody></table>',
        ])->save();

        $this->seed(JsdSolarSeeder::class);

        $product->refresh();
        $this->assertStringContainsString('WiFi', $product->specifications);
        $this->assertStringContainsString('WiFi', $product->name);
    }

    public function test_a_seeded_product_page_opens(): void
    {
        $product = Product::where('sku', 'JSD-J6500HC')->firstOrFail();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('J6500HC')
            ->assertSee('MPPT');
    }

    public function test_running_it_again_does_not_duplicate(): void
    {
        $this->seed(JsdSolarSeeder::class);

        $this->assertCount(18, $this->products());
    }
}
