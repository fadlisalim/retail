<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Limiter login: ketat per email (anti brute-force satu akun), longgar per IP
 * supaya kantor/jaringan yang berbagi IP tidak saling mengunci ("429 Too Many
 * Requests" saat pembeli lain mau masuk).
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email): TestResponse
    {
        return $this->post('/masuk', ['email' => $email, 'password' => 'salah-terus']);
    }

    public function test_hammering_one_email_gets_throttled(): void
    {
        foreach (range(1, 8) as $i) {
            $this->attempt('target@test.id')->assertRedirect();
        }

        $this->attempt('target@test.id')->assertStatus(429);
    }

    public function test_other_buyers_on_the_same_ip_can_still_log_in(): void
    {
        // Satu email dihajar sampai kena limit per-email…
        foreach (range(1, 9) as $i) {
            $this->attempt('target@test.id');
        }

        // …tapi pembeli lain dari IP yang sama tetap bisa masuk.
        $user = User::factory()->create([
            'email' => 'pembeli-lain@test.id',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->post('/masuk', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_shared_ip_cap_still_stops_a_flood(): void
    {
        // 40 percobaan dengan email berbeda-beda dari satu IP → percobaan ke-41
        // tetap diblokir (proteksi banjir dari satu mesin).
        foreach (range(1, 40) as $i) {
            $this->attempt("acak{$i}@test.id");
        }

        $this->attempt('acak-terakhir@test.id')->assertStatus(429);
    }
}
