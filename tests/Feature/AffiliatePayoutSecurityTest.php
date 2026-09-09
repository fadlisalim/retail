<?php

namespace Tests\Feature;

use App\Enums\AffiliateStatus;
use App\Enums\PayoutStatus;
use App\Mail\AffiliateBankChangedMail;
use App\Mail\AffiliatePayoutConfirmMail;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Keamanan penarikan dana afiliator: konfirmasi via link email (signed URL),
 * saldo tidak terkunci oleh permintaan kedaluwarsa, admin tidak bisa
 * memproses yang belum dikonfirmasi, dan perubahan rekening diberi tahu email.
 */
class AffiliatePayoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_staff' => false, 'is_active' => true]);
        $this->affiliate = Affiliate::create([
            'user_id' => $this->user->id, 'code' => 'AMAN99', 'status' => AffiliateStatus::Active->value,
            'full_name' => 'Afiliator Aman', 'id_number' => '123', 'phone' => '0812', 'address' => 'Jkt', 'channel' => 'IG',
            'bank_name' => 'BCA', 'bank_account_number' => '1234567890', 'bank_account_holder' => 'Afiliator Aman',
        ]);

        // Komisi disetujui Rp 500.000 — saldo siap tarik.
        $order = Order::create([
            'order_number' => 'ORD-PAYOUT-'.Str::upper(Str::random(4)),
            'public_token' => Str::uuid(),
            'customer_name' => 'Pembeli', 'customer_email' => 'p@test.id', 'customer_phone' => '0813',
            'status' => 'completed', 'payment_status' => 'paid',
            'items_subtotal' => 10_000_000, 'tax_amount' => 0, 'grand_total' => 10_000_000,
        ]);
        $this->affiliate->commissions()->create([
            'order_id' => $order->id, 'base_amount' => 10_000_000, 'rate' => 5, 'amount' => 500_000, 'status' => 'approved',
        ]);
    }

    public function test_a_request_awaits_email_confirmation_and_sends_the_link(): void
    {
        Mail::fake();

        $this->actingAs($this->user)
            ->post(route('account.affiliate.payout'), ['amount' => 200_000])
            ->assertRedirect()->assertSessionHas('success');

        $payout = $this->affiliate->payouts()->first();
        $this->assertSame(PayoutStatus::AwaitingConfirmation, $payout->status);
        $this->assertNull($payout->confirmed_at);
        // Saldo langsung tercadangkan supaya tidak bisa diajukan dobel.
        $this->assertEquals(300_000, $this->affiliate->availableBalance());

        Mail::assertSent(AffiliatePayoutConfirmMail::class, function (AffiliatePayoutConfirmMail $mail) use ($payout) {
            return $mail->hasTo($this->user->email)
                && $mail->payout->is($payout)
                && str_contains($mail->confirmUrl, '/konfirmasi')
                && str_contains($mail->confirmUrl, 'signature=');
        });
    }

    public function test_the_signed_link_confirms_and_queues_the_payout(): void
    {
        Mail::fake();
        $this->actingAs($this->user)->post(route('account.affiliate.payout'), ['amount' => 200_000]);
        $payout = $this->affiliate->payouts()->first();

        $url = URL::temporarySignedRoute('account.affiliate.payout.confirm', now()->addHours(24), ['payout' => $payout->id]);

        // Tanpa login pun boleh — link hanya ada di inbox pemilik akun.
        $this->post(route('logout'));
        $this->get($url)->assertRedirect(route('account.affiliate.dashboard'));

        $payout->refresh();
        $this->assertSame(PayoutStatus::Requested, $payout->status);
        $this->assertNotNull($payout->confirmed_at);
    }

    public function test_an_unsigned_or_tampered_link_is_rejected(): void
    {
        Mail::fake();
        $this->actingAs($this->user)->post(route('account.affiliate.payout'), ['amount' => 200_000]);
        $payout = $this->affiliate->payouts()->first();

        $this->get(route('account.affiliate.payout.confirm', ['payout' => $payout->id]))->assertForbidden();
        $this->assertSame(PayoutStatus::AwaitingConfirmation, $payout->fresh()->status);
    }

    public function test_an_expired_unconfirmed_request_frees_the_balance(): void
    {
        $payout = $this->affiliate->payouts()->create([
            'amount' => 200_000, 'status' => PayoutStatus::AwaitingConfirmation,
            'method' => 'bank_transfer', 'bank_name' => 'BCA', 'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Afiliator Aman', 'requested_at' => now()->subDays(2),
        ]);
        $payout->forceFill(['created_at' => now()->subDays(2)])->save();

        // Lewat 24 jam tanpa konfirmasi → tidak lagi mengunci saldo.
        $this->assertEquals(500_000, $this->affiliate->availableBalance());
    }

    public function test_admin_cannot_approve_an_unconfirmed_payout(): void
    {
        Mail::fake();
        $this->actingAs($this->user)->post(route('account.affiliate.payout'), ['amount' => 200_000]);
        $payout = $this->affiliate->payouts()->first();

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        $this->actingAs($admin)
            ->post(route('admin.affiliates.payouts.approve', $payout))
            ->assertStatus(422);
    }

    public function test_updating_the_bank_account_notifies_by_email(): void
    {
        Mail::fake();

        $this->actingAs($this->user)->put(route('account.affiliate.bank'), [
            'bank_name' => 'Mandiri',
            'bank_account_number' => '9988776655',
            'bank_account_holder' => 'Afiliator Aman',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Mandiri', $this->affiliate->fresh()->bank_name);
        Mail::assertSent(AffiliateBankChangedMail::class, fn ($mail) => $mail->hasTo($this->user->email));
    }

    public function test_a_failed_confirmation_email_cancels_the_request(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($this->user)
            ->post(route('account.affiliate.payout'), ['amount' => 200_000])
            ->assertRedirect()->assertSessionHasErrors('amount');

        // Penarikan dibatalkan — saldo tidak terkunci tanpa email konfirmasi.
        $this->assertSame(0, $this->affiliate->payouts()->count());
        $this->assertEquals(500_000, $this->affiliate->availableBalance());
    }
}
