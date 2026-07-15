<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\AffiliateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
            'public_token' => \Illuminate\Support\Str::uuid(),
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

        // Idempotent: recording again does not duplicate
        $svc->recordCommissions($order);
        $this->assertEquals(1, $affiliate->commissions()->count());

        // Complete → approved → withdrawable
        $svc->approveCommissions($order);
        $this->assertEquals(150_000, $affiliate->approvedTotal());
        $this->assertEquals(150_000, $affiliate->availableBalance());

        // Request payout (above the 100k minimum)
        $payout = $svc->requestPayout($affiliate, 150_000);
        $this->assertSame(PayoutStatus::Requested, $payout->status);
        $this->assertEquals(0, $affiliate->availableBalance()); // reserved

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
            'public_token' => \Illuminate\Support\Str::uuid(),
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

    public function test_admin_can_verify_pending_affiliate(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'admin-keuangan')->first());

        $affiliate = $this->activeAffiliate(['status' => AffiliateStatus::Pending, 'verified_at' => null]);

        $this->actingAs($admin)
            ->post(route('admin.affiliates.verify', $affiliate))
            ->assertRedirect();

        $this->assertSame(AffiliateStatus::Active, $affiliate->fresh()->status);
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

    public function test_customer_can_apply_as_affiliate(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.affiliate.store'), [
            'full_name' => 'Budi Afiliasi',
            'id_number' => '3200000000000001',
            'phone' => '08123456789',
            'address' => 'Jl. Test No. 1',
            'npwp' => '09.876.543.2-101.000',
            'ktp_photo' => \Illuminate\Http\UploadedFile::fake()->image('ktp.jpg'),
            'selfie_photo' => \Illuminate\Http\UploadedFile::fake()->image('selfie.jpg'),
            'bank_name' => 'BCA',
            'bank_account_number' => '9876543210',
            'bank_account_holder' => 'Budi Afiliasi',
            'agree' => '1',
        ])->assertRedirect(route('account.affiliate.dashboard'));

        $affiliate = $user->fresh()->affiliate;
        $this->assertNotNull($affiliate);
        $this->assertSame(AffiliateStatus::Pending, $affiliate->status);
        $this->assertNotEmpty($affiliate->code);
        // KYC photos stored privately.
        $this->assertNotNull($affiliate->ktp_photo_path);
        $this->assertNotNull($affiliate->selfie_photo_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($affiliate->ktp_photo_path);
    }

    public function test_application_requires_npwp_and_photos(): void
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
        ])->assertSessionHasErrors(['npwp', 'ktp_photo', 'selfie_photo']);

        $this->assertNull($user->fresh()->affiliate);
    }
}
