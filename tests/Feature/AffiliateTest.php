<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AffiliateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateTest extends TestCase
{
    use RefreshDatabase;

    private function activeAffiliate(array $overrides = []): Affiliate
    {
        $user = $this->customer();

        return Affiliate::create(array_merge([
            'user_id' => $user->id,
            'code' => 'AFF'.$user->id,
            'status' => AffiliateStatus::Active,
            'full_name' => 'Afiliator Uji',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Afiliator Uji',
            'verified_at' => now(),
        ], $overrides));
    }

    private function orderWithItem(Affiliate $affiliate, float $lineTotal, ?float $rate = 7.5): Order
    {
        $buyer = $this->customer();
        $product = $this->stockedProduct(10, ['affiliate_rate' => $rate]);

        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'public_token' => Str::uuid(),
            'user_id' => $buyer->id,
            'affiliate_id' => $affiliate->id,
            'customer_name' => $buyer->name,
            'customer_email' => $buyer->email,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => $lineTotal,
            'quantity' => 1,
            'line_total' => $lineTotal,
        ]);

        return $order->fresh('items');
    }

    public function test_commission_lifecycle_and_payout(): void
    {
        Notification::fake();
        $svc = app(AffiliateService::class);
        $affiliate = $this->activeAffiliate();
        $order = $this->orderWithItem($affiliate, 2_000_000, 7.5);

        // Record on payment → pending, 7.5% of 2,000,000 = 150,000
        $svc->recordCommissions($order);
        $commission = $affiliate->commissions()->first();
        $this->assertNotNull($commission);
        $this->assertEquals(150_000, (float) $commission->amount);
        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertEquals(150_000, $affiliate->pendingTotal());
        $this->assertEquals(0, $affiliate->availableBalance());

        // Afiliator dikabari (in-app/email/WA): transaksi dari link-nya dibayar, komisi ditahan.
        Notification::assertSentTo($affiliate->user, SystemNotification::class, fn ($n) => str_contains($n->message, $order->order_number)
            && str_contains($n->message, 'Rp 150.000') && str_contains($n->message, 'ditahan'));

        // Idempotent: recording again does not duplicate (dan tidak mengirim kabar dua kali)
        $svc->recordCommissions($order);
        $this->assertEquals(1, $affiliate->commissions()->count());
        Notification::assertSentToTimes($affiliate->user, SystemNotification::class, 1);

        // Complete → approved → withdrawable (+ kabar "siap ditarik")
        $svc->approveCommissions($order);
        $this->assertEquals(150_000, $affiliate->approvedTotal());
        $this->assertEquals(150_000, $affiliate->availableBalance());
        Notification::assertSentTo($affiliate->user, SystemNotification::class, fn ($n) => str_contains($n->title, 'siap ditarik')
            && str_contains($n->message, 'Rp 150.000'));
        Notification::assertSentToTimes($affiliate->user, SystemNotification::class, 2);
        $svc->approveCommissions($order); // tidak ada yang berubah → tidak ada kabar baru
        Notification::assertSentToTimes($affiliate->user, SystemNotification::class, 2);

        // Request payout (above the 100k minimum) — menunggu konfirmasi email,
        // saldo langsung tercadangkan sejak diajukan.
        $payout = $svc->requestPayout($affiliate, 150_000);
        $this->assertSame(PayoutStatus::AwaitingConfirmation, $payout->status);
        $this->assertEquals(0, $affiliate->availableBalance()); // reserved

        // Konfirmasi via link email (disimulasikan) → masuk antrean keuangan.
        $payout->update(['status' => PayoutStatus::Requested, 'confirmed_at' => now()]);

        // Settle → commission becomes paid
        $svc->settlePayout($payout, null, 'TRX-1');
        $this->assertSame(PayoutStatus::Paid, $payout->fresh()->status);
        $this->assertEquals(150_000, $affiliate->paidTotal());
        $this->assertEquals(0, $affiliate->approvedTotal());
    }

    public function test_cancel_voids_commissions(): void
    {
        $svc = app(AffiliateService::class);
        $affiliate = $this->activeAffiliate();
        $order = $this->orderWithItem($affiliate, 500_000, 10);

        $svc->recordCommissions($order);
        $svc->cancelCommissions($order);

        $this->assertSame(CommissionStatus::Cancelled, $affiliate->commissions()->first()->status);
        $this->assertEquals(0, $affiliate->availableBalance());
    }

    public function test_default_rate_used_when_product_has_none(): void
    {
        $svc = app(AffiliateService::class);
        $affiliate = $this->activeAffiliate();
        $order = $this->orderWithItem($affiliate, 1_000_000, null); // no product rate

        $svc->recordCommissions($order);
        // default 2.5% of 1,000,000 = 25,000
        $this->assertEquals(25_000, (float) $affiliate->commissions()->first()->amount);
    }

    public function test_self_referral_is_not_attributed(): void
    {
        $svc = app(AffiliateService::class);
        $affiliate = $this->activeAffiliate();

        // Buyer IS the affiliate's own user
        $order = Order::create([
            'order_number' => 'ORD-SELF',
            'public_token' => Str::uuid(),
            'user_id' => $affiliate->user_id,
            'customer_name' => 'x', 'customer_email' => 'x@x.test',
        ]);

        $req = Request::create('/checkout', 'POST');
        $req->cookies->set(AffiliateService::COOKIE, $affiliate->code);
        $this->app->instance('request', $req);

        $svc->attributeOrder($order);
        $this->assertNull($order->fresh()->affiliate_id);
    }

    public function test_referral_click_sets_cookie_for_active_affiliate(): void
    {
        $affiliate = $this->activeAffiliate();

        $this->get('/?ref='.$affiliate->code)
            ->assertOk()
            ->assertCookie(AffiliateService::COOKIE, $affiliate->code);

        $this->assertEquals(1, $affiliate->clicks()->count());
    }

    public function test_inactive_affiliate_code_sets_no_cookie(): void
    {
        $affiliate = $this->activeAffiliate(['status' => AffiliateStatus::Pending]);

        $this->get('/?ref='.$affiliate->code)
            ->assertCookieMissing(AffiliateService::COOKIE);
    }

    /**
     * Atribusi klik pertama: pengenal awal tidak bisa direbut oleh klik kode
     * lain — termasuk pembeli yang mendaftar jadi afiliator lalu mengeklik
     * link sendiri agar komisi si pengenal hangus.
     */
    public function test_first_click_attribution_is_not_stolen_by_a_later_code(): void
    {
        $first = $this->activeAffiliate();
        $second = $this->activeAffiliate();

        // Cookie $first masih hidup; klik kode lain TIDAK mengganti cookie.
        $this->withCookie(AffiliateService::COOKIE, $first->code)
            ->get('/?ref='.$second->code)
            ->assertCookieMissing(AffiliateService::COOKIE);

        // Kliknya tetap tercatat untuk statistik $second.
        $this->assertEquals(1, $second->clicks()->count());
    }

    public function test_a_repeat_click_of_the_same_code_refreshes_the_window(): void
    {
        $affiliate = $this->activeAffiliate();

        $this->withCookie(AffiliateService::COOKIE, $affiliate->code)
            ->get('/?ref='.$affiliate->code)
            ->assertCookie(AffiliateService::COOKIE, $affiliate->code);
    }

    /** Cookie milik afiliator yang sudah nonaktif boleh digantikan kode aktif. */
    public function test_a_dead_referrers_cookie_can_be_replaced(): void
    {
        $suspended = $this->activeAffiliate(['status' => AffiliateStatus::Suspended]);
        $active = $this->activeAffiliate();

        $this->withCookie(AffiliateService::COOKIE, $suspended->code)
            ->get('/?ref='.$active->code)
            ->assertCookie(AffiliateService::COOKIE, $active->code);
    }

    /**
     * Skenario lengkap: pembeli datang lewat link A, lalu jadi afiliator dan
     * mengeklik link sendiri — pesanan tetap teratribusi ke A.
     */
    public function test_buyer_turned_affiliate_cannot_void_the_original_referrer(): void
    {
        $original = $this->activeAffiliate();
        $buyerUser = $this->customer();
        $buyerAffiliate = Affiliate::create([
            'user_id' => $buyerUser->id, 'code' => 'SELF01', 'status' => AffiliateStatus::Active,
            'full_name' => 'Pembeli Nakal', 'verified_at' => now(),
        ]);

        // Klik link sendiri saat cookie A masih hidup → cookie tidak berubah.
        $this->withCookie(AffiliateService::COOKIE, $original->code)
            ->get('/?ref='.$buyerAffiliate->code)
            ->assertCookieMissing(AffiliateService::COOKIE);

        // Pesanan dibuat dengan cookie A → teratribusi ke A, bukan hangus.
        $order = Order::create([
            'order_number' => 'ORD-FIRSTCLICK', 'public_token' => Str::uuid(),
            'user_id' => $buyerUser->id, 'customer_name' => 'Pembeli Nakal', 'customer_email' => 'nakal@test.id',
            'status' => OrderStatus::AwaitingPayment->value,
            'payment_status' => PaymentStatus::Unpaid->value,
        ]);
        request()->cookies->set(AffiliateService::COOKIE, $original->code);
        app(AffiliateService::class)->attributeOrder($order);

        $this->assertSame($original->id, $order->fresh()->affiliate_id);
    }

    public function test_admin_can_verify_pending_affiliate(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'admin-keuangan')->first());

        $affiliate = $this->activeAffiliate(['status' => AffiliateStatus::Pending, 'verified_at' => null]);
        Notification::fake();

        $this->actingAs($admin)
            ->post(route('admin.affiliates.verify', $affiliate))
            ->assertRedirect();

        $this->assertSame(AffiliateStatus::Active, $affiliate->fresh()->status);

        // Notifikasi persetujuan menyertakan link PDF panduan — di email sebagai tautan yang bisa diklik.
        Notification::assertSentTo($affiliate->user, SystemNotification::class, function ($notification) use ($affiliate) {
            $html = (string) $notification->toMail($affiliate->user)->render();

            return str_contains($notification->message, asset('panduan-afiliator.pdf'))
                && str_contains($notification->message, $affiliate->code)
                && str_contains($html, 'href="'.asset('panduan-afiliator.pdf').'"');
        });
    }

    public function test_admin_reject_requires_reason_and_notifies_applicant(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'admin-keuangan')->first());

        $affiliate = $this->activeAffiliate(['status' => AffiliateStatus::Pending, 'verified_at' => null]);

        // Missing reason → validation error, status unchanged.
        $this->actingAs($admin)
            ->post(route('admin.affiliates.reject', $affiliate))
            ->assertSessionHasErrors('note');
        $this->assertSame(AffiliateStatus::Pending, $affiliate->fresh()->status);

        // With reason → rejected, note stored, applicant notified.
        $this->actingAs($admin)
            ->post(route('admin.affiliates.reject', $affiliate), ['note' => 'Foto selfie tidak memegang KTP.'])
            ->assertRedirect();

        $fresh = $affiliate->fresh();
        $this->assertSame(AffiliateStatus::Rejected, $fresh->status);
        $this->assertSame('Foto selfie tidak memegang KTP.', $fresh->note);

        Notification::assertSentTo(
            $affiliate->user,
            SystemNotification::class,
            fn ($n) => str_contains($n->message, 'Foto selfie tidak memegang KTP.') && $n->email === true,
        );
    }

    public function test_rejected_applicant_can_reapply(): void
    {
        Storage::fake('local');
        $affiliate = $this->activeAffiliate([
            'status' => AffiliateStatus::Rejected,
            'note' => 'Data kurang lengkap',
            'ktp_photo_path' => 'affiliate-kyc/old-ktp.jpg',
            'selfie_photo_path' => 'affiliate-kyc/old-selfie.jpg',
        ]);
        Storage::disk('local')->put('affiliate-kyc/old-ktp.jpg', 'x');

        $this->actingAs($affiliate->user)->post(route('account.affiliate.store'), [
            'full_name' => 'Budi Afiliasi',
            'id_number' => '3200000000000001',
            'phone' => '08123456789',
            'address' => 'Jl. Test No. 1',
            'npwp' => '09.876.543.2-101.000',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
            'selfie_photo' => UploadedFile::fake()->image('selfie.jpg'),
            'bank_name' => 'BCA',
            'bank_account_number' => '9876543210',
            'bank_account_holder' => 'Budi Afiliasi',
            'agree' => '1',
        ])->assertRedirect(route('account.affiliate.dashboard'));

        $fresh = $affiliate->fresh();
        // Same record reused, reset to pending, note cleared, old KTP file removed.
        $this->assertSame(AffiliateStatus::Pending, $fresh->status);
        $this->assertNull($fresh->note);
        $this->assertEquals(1, Affiliate::where('user_id', $affiliate->user_id)->count());
        Storage::disk('local')->assertMissing('affiliate-kyc/old-ktp.jpg');
    }

    public function test_pages_render(): void
    {
        // Public landing
        $this->get(route('affiliate.landing'))->assertOk()->assertSee('Program Afiliasi');

        // Customer dashboard
        $affiliate = $this->activeAffiliate();
        $this->actingAs($affiliate->user)
            ->get(route('account.affiliate.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Afiliasi')
            ->assertSee($affiliate->code);

        // Admin index + payouts
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'admin-keuangan')->first());
        $this->actingAs($admin)->get(route('admin.affiliates.index'))->assertOk()->assertSee($affiliate->full_name);
        $this->actingAs($admin)->get(route('admin.affiliates.payouts'))->assertOk();
        $this->actingAs($admin)->get(route('admin.affiliates.show', $affiliate))->assertOk();
    }

    public function test_admin_can_delete_clean_affiliate_but_not_one_with_commissions(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'admin-keuangan')->first());

        // Clean affiliate → deletable
        $clean = $this->activeAffiliate();
        $this->actingAs($admin)->delete(route('admin.affiliates.destroy', $clean))->assertRedirect(route('admin.affiliates.index'));
        $this->assertNull($clean->fresh());

        // Affiliate with a commission → protected
        $withCommission = $this->activeAffiliate();
        app(AffiliateService::class)->recordCommissions($this->orderWithItem($withCommission, 1_000_000, 5));
        $this->actingAs($admin)->delete(route('admin.affiliates.destroy', $withCommission))->assertRedirect();
        $this->assertNotNull($withCommission->fresh());
    }

    public function test_customer_can_apply_as_affiliate(): void
    {
        Storage::fake('local');
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.affiliate.store'), [
            'full_name' => 'Budi Afiliasi',
            'id_number' => '3200000000000001',
            'phone' => '08123456789',
            'address' => 'Jl. Test No. 1',
            'npwp' => '09.876.543.2-101.000',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
            'selfie_photo' => UploadedFile::fake()->image('selfie.jpg'),
            'bank_name' => 'BCA',
            'bank_account_number' => '9876543210',
            'bank_account_holder' => 'Budi Afiliasi',
            'agree' => '1',
        ])->assertRedirect(route('account.affiliate.dashboard'));

        $affiliate = $user->fresh()->affiliate;
        $this->assertNotNull($affiliate);
        $this->assertSame(AffiliateStatus::Pending, $affiliate->status);
        $this->assertSame(6, strlen($affiliate->code));
        // KYC photos stored privately.
        $this->assertNotNull($affiliate->ktp_photo_path);
        $this->assertNotNull($affiliate->selfie_photo_path);
        Storage::disk('local')->assertExists($affiliate->ktp_photo_path);
    }

    public function test_application_requires_photos_but_not_npwp(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.affiliate.store'), [
            'full_name' => 'Tanpa Dokumen',
            'id_number' => '3200000000000002',
            'phone' => '08123456789',
            'address' => 'Jl. Test No. 2',
            'bank_name' => 'BCA',
            'bank_account_number' => '9876543210',
            'bank_account_holder' => 'Tanpa Dokumen',
            'agree' => '1',
        ])
            ->assertSessionHasErrors(['ktp_photo', 'selfie_photo'])  // photos still required
            ->assertSessionDoesntHaveErrors('npwp');                // NPWP no longer required

        $this->assertNull($user->fresh()->affiliate);
    }
}
