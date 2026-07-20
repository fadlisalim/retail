<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizePhonesTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_legacy_local_numbers_and_leaves_international_alone(): void
    {
        $legacy = User::factory()->create(['whatsapp' => '08123456789', 'phone' => '0811-2233-4455']);
        $intl = User::factory()->create(['whatsapp' => '628999888777', 'phone' => '60123456789']);

        $this->artisan('users:normalize-phones')->assertSuccessful();

        $legacy->refresh();
        $this->assertSame('628123456789', $legacy->whatsapp);
        $this->assertSame('6281122334455', $legacy->phone);

        // Already international → untouched.
        $intl->refresh();
        $this->assertSame('628999888777', $intl->whatsapp);
        $this->assertSame('60123456789', $intl->phone);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $user = User::factory()->create(['whatsapp' => '08123456789']);

        $this->artisan('users:normalize-phones --dry-run')->assertSuccessful();

        $this->assertSame('08123456789', $user->fresh()->whatsapp);
    }
}
