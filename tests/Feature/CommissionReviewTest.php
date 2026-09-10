<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AffiliateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review komisi atribusi manual: hanya super admin; setujui → cair/ditahan
 * sesuai status pesanan; tolak → dibatalkan dengan catatan; komisi dari link
 * referral tidak pernah lewat review.
 */
class CommissionReviewTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function affiliate(): Affiliate
    {
        $user = $this->customer();

        return Affiliate::create([
            'user_id' => $user->id, 'code' => 'AFF'.$user->id, 'status' => AffiliateStatus::Active, 'full_name' => 'Afiliator Uji',
            'bank_name' => 'BCA', 'bank_account_number' => '123', 'bank_account_holder' => 'Afiliator Uji', 'verified_at' => now(),
        ]);
    }

    private function paidOrder(Affiliate $affiliate, string $source, ?int $attributedBy = null, string $status = 'processing'): Order
    {
        $buyer = $this->customer();
        $product = Product::factory()->create(['affiliate_rate' => 5]);
        $order = Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(5)), 'public_token' => Str::uuid(),
            'user_id' => $buyer->id, 'affiliate_id' => $affiliate->id, 'affiliate_source' => $source, 'affiliate_attributed_by' => $attributedBy,
            'customer_name' => $buyer->name, 'customer_email' => $buyer->email,
            'status' => $status, 'payment_status' => 'paid', 'grand_total' => 1_000_000,
        ]);
        $order->items()->create(['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'unit_price' => 1_000_000, 'quantity' => 1, 'line_total' => 1_000_000]);

        return $order->fresh('items');
    }

    public function test_referral_commissions_skip_review_but_manual_ones_wait(): void
    {
        $affiliate = $this->affiliate();
        $service = app(AffiliateService::class);

        $referral = $this->paidOrder($affiliate, 'referral');
        $service->recordCommissions($referral);
        $this->assertSame(CommissionStatus::Pending, $referral->affiliateCommissions()->firstOrFail()->status);

        $admin = $this->staff('admin-sales');
        $manual = $this->paidOrder($affiliate, 'manual', $admin->id);
        $service->recordCommissions($manual);
        $commission = $manual->affiliateCommissions()->firstOrFail();
        $this->assertSame(CommissionStatus::AwaitingReview, $commission->status);
        $this->assertSame($admin->id, $commission->attributed_by);
        // Saldo ditahan hanya dari komisi referral (50rb); yang menunggu review tidak dihitung.
        $this->assertEquals(50_000, $affiliate->pendingTotal());
    }

    public function test_only_super_admin_can_open_the_review_page_or_decide(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->paidOrder($affiliate, 'manual');
        app(AffiliateService::class)->recordCommissions($order);
        $commission = $order->affiliateCommissions()->firstOrFail();

        // Admin keuangan punya affiliate.manage, tapi bukan super admin.
        $finance = $this->staff('admin-keuangan');
        $this->actingAs($finance)->get(route('admin.affiliates.commissions.review'))->assertForbidden();
        $this->actingAs($finance)->post(route('admin.affiliates.commissions.approve', $commission))->assertForbidden();
        $this->assertSame(CommissionStatus::AwaitingReview, $commission->fresh()->status);

        $super = $this->staff('super-admin');
        $this->actingAs($super)->get(route('admin.affiliates.commissions.review'))
            ->assertOk()->assertSee($order->order_number)->assertSee('Afiliator Uji')->assertSee('Setujui');
        $this->actingAs($super)->get(route('admin.affiliates.index'))->assertOk()->assertSee('Review Komisi');
    }

    public function test_reject_cancels_with_a_note_and_approve_is_idempotent(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->paidOrder($affiliate, 'manual', status: 'completed');
        app(AffiliateService::class)->recordCommissions($order);
        $commission = $order->affiliateCommissions()->firstOrFail();
        $super = $this->staff('super-admin');

        $this->actingAs($super)->post(route('admin.affiliates.commissions.reject', $commission), [])->assertSessionHasErrors('note');
        $this->actingAs($super)->post(route('admin.affiliates.commissions.reject', $commission), ['note' => 'Bukan penjualan afiliator'])->assertRedirect();
        $commission->refresh();
        $this->assertSame(CommissionStatus::Cancelled, $commission->status);
        $this->assertSame('Bukan penjualan afiliator', $commission->review_note);
        $this->assertSame($super->id, $commission->reviewed_by);

        // Sudah direview → tidak bisa disetujui belakangan.
        $this->actingAs($super)->post(route('admin.affiliates.commissions.approve', $commission))->assertSessionHas('error');
        $this->assertSame(CommissionStatus::Cancelled, $commission->fresh()->status);
        $this->assertEquals(0, $affiliate->availableBalance());
    }

    public function test_cancelling_the_order_voids_commissions_still_under_review(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->paidOrder($affiliate, 'manual');
        app(AffiliateService::class)->recordCommissions($order);

        app(AffiliateService::class)->cancelCommissions($order);

        $this->assertSame(CommissionStatus::Cancelled, AffiliateCommission::where('order_id', $order->id)->firstOrFail()->status);
    }
}
