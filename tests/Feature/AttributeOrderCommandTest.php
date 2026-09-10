<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * affiliate:attribute — atribusi manual: komisi masuk "menunggu review",
 * super admin yang meloloskan; fee khusus via --fee; idempotent; tolak
 * pembelian sendiri dan pemindahan tanpa --force.
 */
class AttributeOrderCommandTest extends TestCase
{
    use RefreshDatabase;

    private function affiliate(string $code = 'eU5ACZ', AffiliateStatus $status = AffiliateStatus::Active): Affiliate
    {
        $user = $this->customer(['name' => 'Salman Al Ghifari']);

        return Affiliate::create([
            'user_id' => $user->id, 'code' => $code, 'status' => $status, 'full_name' => $user->name,
            'bank_name' => 'BCA', 'bank_account_number' => '123', 'bank_account_holder' => $user->name, 'verified_at' => now(),
        ]);
    }

    private function superAdmin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        return $admin;
    }

    private function order(string $status, string $paymentStatus, array $overrides = [], ?float $productRate = 5): Order
    {
        $buyer = $this->customer();
        $product = $this->stockedProduct(10, ['affiliate_rate' => $productRate]);

        $order = Order::create(array_merge([
            'order_number' => 'ORD-'.strtoupper(Str::random(5)), 'public_token' => Str::uuid(),
            'user_id' => $buyer->id, 'customer_name' => $buyer->name, 'customer_email' => $buyer->email,
            'status' => $status, 'payment_status' => $paymentStatus, 'grand_total' => 2_000_000,
        ], $overrides));
        $order->items()->create([
            'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
            'unit_price' => 2_000_000, 'quantity' => 1, 'line_total' => 2_000_000,
        ]);

        return $order->fresh('items');
    }

    public function test_manual_attribution_waits_for_super_admin_review_before_paying_out(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->order('completed', 'paid');

        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ', '--force' => true])
            ->expectsOutputToContain('MENUNGGU REVIEW')
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame($affiliate->id, $order->affiliate_id);
        $this->assertSame('manual', $order->affiliate_source);
        $commission = $order->affiliateCommissions()->firstOrFail();
        $this->assertEquals(100_000, (float) $commission->amount); // 5% × 2.000.000
        $this->assertSame(CommissionStatus::AwaitingReview, $commission->status);
        $this->assertEquals(0, $affiliate->fresh()->availableBalance(), 'Belum boleh cair sebelum direview.');

        // Dijalankan ulang → tidak menggandakan komisi.
        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ', '--force' => true])->assertSuccessful();
        $this->assertSame(1, $order->affiliateCommissions()->count());

        // Super admin menyetujui → pesanan sudah Selesai → langsung cair.
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.affiliates.commissions.approve', $commission))->assertRedirect();
        $commission->refresh();
        $this->assertSame(CommissionStatus::Approved, $commission->status);
        $this->assertSame($admin->id, $commission->reviewed_by);
        $this->assertEquals(100_000, $affiliate->fresh()->availableBalance());
    }

    public function test_fee_override_applies_to_this_order_only(): void
    {
        $this->affiliate();
        $order = $this->order('completed', 'paid', productRate: null); // produk tanpa fee khusus → default toko

        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ', '--fee' => '5', '--force' => true])
            ->assertSuccessful();

        $commission = $order->affiliateCommissions()->firstOrFail();
        $this->assertEquals(5, (float) $commission->rate);
        $this->assertEquals(100_000, (float) $commission->amount);

        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ', '--fee' => '150', '--force' => true])->assertFailed();
    }

    public function test_paid_but_unfinished_order_is_held_after_approval(): void
    {
        $this->affiliate();
        $order = $this->order('shipped', 'paid');

        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ', '--force' => true])->assertSuccessful();
        $commission = $order->affiliateCommissions()->firstOrFail();
        $this->assertSame(CommissionStatus::AwaitingReview, $commission->status);

        $this->actingAs($this->superAdmin())->post(route('admin.affiliates.commissions.approve', $commission));
        $this->assertSame(CommissionStatus::Pending, $commission->fresh()->status);
    }

    public function test_unpaid_order_is_linked_without_commission_yet(): void
    {
        $affiliate = $this->affiliate();
        $order = $this->order('awaiting_payment', 'unpaid');

        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ', '--force' => true])->assertSuccessful();

        $this->assertSame($affiliate->id, $order->fresh()->affiliate_id);
        $this->assertSame('manual', $order->fresh()->affiliate_source);
        $this->assertSame(0, $order->affiliateCommissions()->count());
    }

    public function test_self_purchase_inactive_affiliate_and_other_affiliate_are_refused(): void
    {
        $affiliate = $this->affiliate();
        $own = $this->order('completed', 'paid', ['user_id' => $affiliate->user_id]);
        $this->artisan('affiliate:attribute', ['order_number' => $own->order_number, 'code' => 'eU5ACZ', '--force' => true])->assertFailed();
        $this->assertNull($own->fresh()->affiliate_id);

        $this->affiliate('SUSPND', AffiliateStatus::Suspended);
        $order = $this->order('completed', 'paid');
        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'SUSPND', '--force' => true])->assertFailed();

        $other = $this->affiliate('OTHER1');
        $order->update(['affiliate_id' => $other->id]);
        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ'])->assertFailed();
        $this->assertSame($other->id, $order->fresh()->affiliate_id);

        // --force memindahkan.
        $this->artisan('affiliate:attribute', ['order_number' => $order->order_number, 'code' => 'eU5ACZ', '--force' => true])->assertSuccessful();
        $this->assertSame($affiliate->id, $order->fresh()->affiliate_id);
        $this->assertSame($affiliate->id, $order->affiliateCommissions()->firstOrFail()->affiliate_id);
    }
}
