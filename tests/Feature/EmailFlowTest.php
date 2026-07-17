<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\OrderService;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();
        $user = $this->customer(['email' => 'reset@test.id']);

        $this->post(route('password.email'), ['email' => 'reset@test.id'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_can_be_reset(): void
    {
        $user = $this->customer(['email' => 'reset2@test.id']);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'reset2@test.id',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $this->post('/daftar', [
            'name' => 'Verify Me',
            'email' => 'verify@test.id',
            'whatsapp' => '08123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Notification::assertSentTo(User::where('email', 'verify@test.id')->first(), VerifyEmailNotification::class);
    }

    public function test_unverified_user_is_blocked_from_account(): void
    {
        $user = User::factory()->unverified()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_signed_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create(['is_active' => true]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        // The link works without being logged in (customers verify before first login).
        $this->get($url)->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_order_payment_emails_the_customer(): void
    {
        Notification::fake();
        $buyer = $this->customer();
        $order = Order::create([
            'order_number' => 'ORD-MAIL',
            'public_token' => \Illuminate\Support\Str::uuid(),
            'user_id' => $buyer->id,
            'customer_name' => $buyer->name,
            'customer_email' => $buyer->email,
            'grand_total' => 100000,
        ]);
        $order->payments()->create(['method' => 'manual_transfer', 'status' => 'unpaid', 'amount' => 100000]);

        app(OrderService::class)->markPaid($order);

        Notification::assertSentTo($buyer, SystemNotification::class, function ($n) {
            return $n->email === true && in_array('mail', $n->via($this->customer()));
        });
    }
}
