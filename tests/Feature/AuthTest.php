<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register(): void
    {
        Event::fake();

        $response = $this->post('/daftar', [
            'name' => 'Budi Santoso',
            'email' => 'budi@test.id',
            'whatsapp' => '08123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // New accounts must verify their email before the account area unlocks.
        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'budi@test.id']);
        $this->assertDatabaseHas('customer_profiles', ['user_id' => User::first()->id]);
        Event::assertDispatched(Registered::class);
    }

    public function test_registration_revives_a_soft_deleted_account(): void
    {
        Event::fake();

        // An old account with this email was deleted (soft delete).
        $old = User::factory()->create(['email' => 'kembali@test.id', 'name' => 'Nama Lama']);
        $old->delete();

        $this->post('/daftar', [
            'name' => 'Nama Baru',
            'email' => 'kembali@test.id',
            'whatsapp' => '08123456789',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        // The same row is revived (not a duplicate) with the new details.
        $this->assertSame(1, User::withTrashed()->where('email', 'kembali@test.id')->count());
        $revived = User::where('email', 'kembali@test.id')->first();
        $this->assertNull($revived->deleted_at);
        $this->assertSame('Nama Baru', $revived->name);
    }

    public function test_registration_rejects_an_active_email(): void
    {
        User::factory()->create(['email' => 'aktif@test.id', 'is_active' => true]);

        $this->from('/daftar')->post('/daftar', [
            'name' => 'Coba', 'email' => 'aktif@test.id', 'whatsapp' => '0812',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/daftar')->assertSessionHasErrors('email');
    }

    public function test_customer_can_login(): void
    {
        $user = User::factory()->create(['email' => 'a@test.id', 'password' => 'password123', 'is_active' => true]);

        $this->post('/masuk', ['email' => 'a@test.id', 'password' => 'password123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@test.id', 'password' => 'password123']);

        $this->from('/masuk')->post('/masuk', ['email' => 'a@test.id', 'password' => 'wrong'])
            ->assertRedirect('/masuk')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_records_a_login_activity_absent_for_failed_login(): void
    {
        User::factory()->create(['email' => 'a@test.id', 'password' => 'password123']);
        $this->post('/masuk', ['email' => 'a@test.id', 'password' => 'nope']);

        $this->assertDatabaseHas('login_activities', ['email' => 'a@test.id', 'successful' => false]);
    }
}
