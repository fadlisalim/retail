<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Models\WaContact;
use App\Services\SettingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit UX: menu ke halaman kosong disembunyikan, klaim harga sesuai (tanpa PPN),
 * daftar promo di footer masuk WA Campaign, Clearance konsisten, sidebar admin berkelompok.
 */
class StorefrontAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_categories_and_empty_pages_are_hidden_from_navigation(): void
    {
        $filled = Category::create(['name' => 'Baterai', 'slug' => 'baterai', 'is_active' => true, 'depth' => 0]);
        $child = Category::create(['name' => 'Lithium', 'slug' => 'baterai-lithium', 'parent_id' => $filled->id, 'is_active' => true, 'depth' => 1]);
        $emptyChild = Category::create(['name' => 'Rack Kosong', 'slug' => 'baterai-rack-kosong', 'parent_id' => $filled->id, 'is_active' => true, 'depth' => 1]);
        Category::create(['name' => 'Mounting Kosong', 'slug' => 'mounting-kosong', 'is_active' => true, 'depth' => 0]);
        $this->stockedProduct(5, ['name' => 'Baterai LiFePO4 Uji', 'category_id' => $child->id]);

        $home = $this->get('/')->assertOk();
        $home->assertSee('Lithium')->assertDontSee('Rack Kosong')->assertDontSee('Mounting Kosong');
        $this->get(route('categories.index'))->assertOk()->assertSee('Baterai')->assertDontSee('Mounting Kosong');
        // Kategori kosong tetap bisa dibuka lewat URL langsung (tidak 404), hanya tidak dipajang.
        $this->get(route('categories.show', 'mounting-kosong'))->assertOk();

        // Tidak ada promo / dokumentasi / artikel → link-nya tidak tampil; klaim harga sesuai.
        $home->assertDontSee(route('promo'))->assertDontSee(route('documentation'))->assertDontSee(route('articles.index'));
        $home->assertDontSee('Harga sudah termasuk pajak')->assertSee('Harga belum PPN');
        $home->assertDontSee('Paling Banyak Dilihat')->assertDontSee('Rating Terbaik');
        // Afiliator hanya di footer.
        $this->assertSame(1, substr_count($home->getContent(), 'href="'.route('affiliate.landing').'"'));
        // Ikon perbandingan hanya muncul bila ada produk yang dibandingkan.
        $home->assertDontSee('aria-label="Perbandingan"', false);
    }

    public function test_catalog_filters_only_offer_brands_and_conditions_present_and_home_uses_product_photos_for_categories(): void
    {
        $cat = Category::create(['name' => 'Inverter', 'slug' => 'inverter', 'is_active' => true, 'depth' => 0]);
        $used = Brand::create(['name' => 'Merek Ada', 'slug' => 'merek-ada', 'is_active' => true]);
        Brand::create(['name' => 'Merek Kosong', 'slug' => 'merek-kosong', 'is_active' => true]);
        $this->stockedProduct(3, ['name' => 'Inverter Uji', 'category_id' => $cat->id, 'brand_id' => $used->id, 'condition' => 'new', 'main_image_path' => 'products/inverter-uji.webp']);

        $page = $this->get(route('categories.show', 'inverter'))->assertOk();
        $page->assertSee('Merek Ada')->assertDontSee('Merek Kosong')->assertDontSee('Bekas Display');
        $this->get('/')->assertOk()->assertSee('storage/products/inverter-uji.webp', false)->assertSee('💬 Chat WhatsApp');
    }

    public function test_used_items_show_up_on_the_clearance_page_like_on_the_home_panel(): void
    {
        $this->stockedProduct(5, ['name' => 'Panel Bekas Proyek Uji', 'condition' => 'used', 'is_clearance' => false]);
        $this->get(route('clearance'))->assertOk()->assertSee('Panel Bekas Proyek Uji');
        $this->get('/')->assertSee(route('clearance'));
    }

    public function test_footer_promo_signup_adds_an_opted_in_whatsapp_contact(): void
    {
        app(SettingService::class)->set('whatsapp.number', '628112241107', 'string', 'whatsapp');
        $this->get('/')->assertSee('Info Promo via WhatsApp')->assertSee('Chat WhatsApp CS');

        $this->from('/')->post(route('newsletter.subscribe'), ['phone' => '0812 3456 7890'])->assertRedirect('/')->assertSessionHas('success');
        $c = WaContact::where('phone', '6281234567890')->firstOrFail();
        $this->assertTrue($c->isOptedIn());
        $this->assertSame('footer', $c->source);
        $this->assertStringContainsString('form website', $c->consent_proof);

        $this->from('/')->post(route('newsletter.subscribe'), ['phone' => '123'])->assertSessionHas('error');
        $this->from('/')->post(route('newsletter.subscribe'), [])->assertSessionHasErrors('phone');
    }

    public function test_admin_sidebar_is_grouped_and_hides_kirana_log_when_assistant_is_off(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        $page = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('Penjualan')->assertSee('Katalog &amp; Stok', false)->assertSee('Penawaran / RAB')->assertSee('Log Kirana (AI)');

        app(SettingService::class)->set('chat.assistant_enabled', false, 'boolean', 'chat');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Log Kirana (AI)');
    }

    public function test_shared_home_link_uses_the_energi_click_banner_as_preview_image(): void
    {
        $this->assertFileExists(public_path('images/og-energi-click.jpg'));
        $this->assertSame([1200, 630], array_slice(getimagesize(public_path('images/og-energi-click.jpg')), 0, 2));

        $this->get('/')->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('images/og-energi-click.jpg').'">', false)
            ->assertSee('<meta property="og:image:type" content="image/jpeg">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false);
    }
}
