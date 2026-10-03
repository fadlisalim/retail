<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\GoogleAdsOfflineConversions;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Google Ads: Google tag + event konversi dari Pengaturan, gclid tersimpan per
 * kunjungan dan terkait ke pesanan/RFQ, ekspor CSV konversi offline (pesanan
 * lunas terverifikasi Keuangan).
 */
class GoogleAdsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_ID = 'abcdefghijklmnopqrstuvwxyz01234567890123'; // 40 alnum = session id valid

    private function staff(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function enableGoogleTag(): void
    {
        $s = app(SettingService::class);
        $s->set('marketing.google_tag_id', 'AW-123456789', 'string', 'marketing');
        $s->set('marketing.google_ads_label_lead', 'LeadLbl', 'string', 'marketing');
        $s->set('marketing.google_ads_label_rfq', 'RfqLbl', 'string', 'marketing');
        $s->set('marketing.google_ads_label_order', 'OrderLbl', 'string', 'marketing');
    }

    private function paidOrder(string $number, float $total, ?int $visitId, ?string $verifiedAt): Order
    {
        return Order::create([
            'order_number' => $number, 'public_token' => Str::uuid(), 'site_visit_id' => $visitId,
            'customer_name' => 'Budi', 'customer_email' => 'budi@test.id', 'customer_phone' => '0812',
            'status' => OrderStatus::PaymentVerified->value, 'payment_status' => PaymentStatus::Paid->value,
            'items_subtotal' => $total, 'tax_amount' => 0, 'grand_total' => $total, 'channel' => 'website',
            'paid_at' => $verifiedAt, 'finance_verified_at' => $verifiedAt,
        ]);
    }

    public function test_google_tag_renders_only_when_configured_with_whatsapp_click_listener(): void
    {
        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com/gtag/js');

        $this->enableGoogleTag();
        $this->get('/')->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=AW-123456789', false)
            ->assertSee('window.ecConv', false)
            ->assertSee("tagId + '/' + labels[kind]", false)   // send_to = tag/label
            ->assertSee('LeadLbl')                 // label dari Pengaturan ikut terpasang
            ->assertSee('wa\\.me', false);         // klik tombol WhatsApp = lead
    }

    public function test_gclid_is_stored_on_the_visit_and_linked_to_the_rfq(): void
    {
        $this->enableGoogleTag();
        $product = Product::factory()->quotationOnly()->create();
        $cookie = [config('session.cookie') => self::SESSION_ID];

        $this->withCookies($cookie)->get('/?gclid=Cj0KCQjw_TeSt-123')->assertOk();
        $visit = SiteVisit::firstOrFail();
        $this->assertSame('Cj0KCQjw_TeSt-123', $visit->gclid);
        $this->assertSame('google_ads', $visit->source);

        // Kunjungan sama, klik iklan baru di tengah sesi → gclid diperbarui, sumber tetap.
        $this->withCookies($cookie)->get('/produk?gclid=Second_Click')->assertOk();
        $this->assertSame('Second_Click', $visit->fresh()->gclid);
        $this->assertSame(1, SiteVisit::count());

        $response = $this->withCookies($cookie)->post('/permintaan-penawaran', [
            'contact_name' => 'Budi', 'contact_email' => 'budi@test.id',
            'project_type' => 'plts_rumah', 'project_status' => 'planning',
            'project_location' => 'Bandung, Jawa Barat', 'budget_range' => '25_50',
            'items' => [['name' => $product->name, 'quantity' => 5, 'product_id' => $product->id]],
        ])->assertRedirect();

        $rfq = Quotation::firstOrFail();
        $this->assertSame($visit->id, $rfq->site_visit_id);

        // Halaman penawaran tepat setelah submit memicu konversi "rfq" sekali.
        $this->withCookies($cookie)->get($response->headers->get('Location'))->assertOk()
            ->assertSee("ecConv('rfq'", false)->assertSee($rfq->rfq_number);
        $this->flushSession();
        $this->get(route('quotations.show', $rfq->public_token))->assertOk()->assertDontSee("ecConv('rfq'", false);
    }

    public function test_order_conversion_fires_on_the_payment_page_right_after_checkout(): void
    {
        $this->enableGoogleTag();
        $order = $this->paidOrder('ORD-ADS-1', 2_500_000, null, null);
        $order->forceFill(['status' => OrderStatus::AwaitingPayment->value, 'payment_status' => PaymentStatus::Unpaid->value])->save(); // baru dibuat, belum bayar

        $this->withSession(['success' => 'Pesanan ORD-ADS-1 berhasil dibuat.'])->get(route('orders.pay', $order->public_token))
            ->assertOk()->assertSee("ecConv('order'", false)->assertSee("transaction_id: 'ORD-ADS-1'", false)->assertSee("fbq('track', 'Purchase'", false);
        $this->flushSession(); // dibuka lagi tanpa flash → tidak dihitung dua kali
        $this->get(route('orders.pay', $order->public_token))->assertOk()->assertDontSee("ecConv('order'", false);
        // Tidak lagi dobel di halaman lacak pesanan.
        $this->withSession(['success' => 'Pesanan ORD-ADS-1 berhasil dibuat.'])->get(route('orders.track', $order->public_token))
            ->assertOk()->assertDontSee("fbq('track', 'Purchase'", false);
    }

    public function test_offline_conversion_export_lists_only_verified_paid_orders_from_google_clicks(): void
    {
        $this->seed(RoleSeeder::class);
        app(SettingService::class)->set('marketing.google_ads_offline_name', 'Pesanan Lunas', 'string', 'marketing');
        $google = SiteVisit::create(['session_token' => 'g1', 'source' => 'google_ads', 'gclid' => 'GcLiD_abc-1', 'created_at' => now()->subDays(5)]);
        $direct = SiteVisit::create(['session_token' => 'd1', 'source' => 'direct', 'created_at' => now()->subDays(5)]);

        $ok = $this->paidOrder('ORD-G-OK', 4_318_000, $google->id, '2026-10-02 14:30:00');
        $this->paidOrder('ORD-G-OLD', 1_000_000, $google->id, '2026-06-01 10:00:00');       // di luar rentang
        $this->paidOrder('ORD-DIRECT', 2_000_000, $direct->id, '2026-10-02 15:00:00');       // tanpa gclid
        $unverified = $this->paidOrder('ORD-G-UNVERIFIED', 3_000_000, $google->id, null);   // belum dikonfirmasi Keuangan
        $unverified->forceFill(['paid_at' => '2026-10-02 16:00:00'])->save();

        $rows = app(GoogleAdsOfflineConversions::class)->rows(CarbonImmutable::parse('2026-09-15'), CarbonImmutable::parse('2026-10-03'));
        $this->assertStringStartsWith('Parameters:TimeZone=+', $rows[0][0]);
        $this->assertSame(['Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency'], $rows[1]);
        $this->assertCount(3, $rows);
        $this->assertSame(['GcLiD_abc-1', 'Pesanan Lunas', '2026-10-02 14:30:00', '4318000', 'IDR'], $rows[2]);

        // Unduhan CSV untuk Keuangan; sales ditolak.
        $finance = $this->staff('admin-keuangan');
        $csv = $this->actingAs($finance)->get(route('admin.reports.google-ads', ['dari' => '2026-09-15', 'sampai' => '2026-10-03']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('GcLiD_abc-1,"Pesanan Lunas","2026-10-02 14:30:00",4318000,IDR', $csv->streamedContent());
        $this->assertStringNotContainsString('ORD-DIRECT', $csv->streamedContent());
        $this->actingAs($finance)->get(route('admin.reports.monthly'))->assertOk()->assertSee('Konversi Google Ads');
        $this->actingAs($this->staff('admin-sales'))->get(route('admin.reports.google-ads'))->assertForbidden();

        // Perintah artisan menghasilkan CSV yang sama.
        $this->artisan('googleads:konversi', ['--dari' => '2026-09-15', '--sampai' => '2026-10-03'])
            ->expectsOutputToContain('GcLiD_abc-1')->expectsOutputToContain('1 konversi')->assertSuccessful();
    }

    public function test_admin_can_save_google_ads_settings_and_invalid_tag_id_is_rejected(): void
    {
        $admin = $this->staff('super-admin');
        $base = ['tax_ppn_percent' => 11];

        $this->actingAs($admin)->put(route('admin.settings.update'), $base + [
            'marketing_google_tag_id' => 'AW-987654321', 'marketing_google_ads_label_lead' => 'abcDEF_12-x',
            'marketing_google_ads_offline_name' => 'Pesanan Lunas Energi',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $s = app(SettingService::class);
        $this->assertSame('AW-987654321', $s->get('marketing.google_tag_id'));
        $this->assertSame('abcDEF_12-x', $s->get('marketing.google_ads_label_lead'));
        $this->assertSame('Pesanan Lunas Energi', $s->get('marketing.google_ads_offline_name'));

        $this->actingAs($admin)->put(route('admin.settings.update'), $base + ['marketing_google_tag_id' => 'UA-123'])
            ->assertSessionHasErrors('marketing_google_tag_id');
        $this->actingAs($admin)->put(route('admin.settings.update'), $base + ['marketing_google_ads_label_rfq' => 'bad label!'])
            ->assertSessionHasErrors('marketing_google_ads_label_rfq');
    }
}
