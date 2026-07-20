<?php

namespace Tests\Feature;

use App\Notifications\Channels\WablasChannel;
use App\Notifications\SystemNotification;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function enableWablas(): void
    {
        config([
            'services.wablas.enabled' => true,
            'services.wablas.token' => 'test-token.secret',
            'services.wablas.base_url' => 'https://solo.wablas.com',
        ]);
    }

    public function test_phone_is_normalised_to_indonesian_format(): void
    {
        $wa = app(WhatsAppService::class);
        $this->assertSame('628123456789', $wa->normalize('08123456789'));       // legacy 0 → 62
        $this->assertSame('628123456789', $wa->normalize('+62 812-3456-789'));  // strips +/spaces
        $this->assertSame('628123456789', $wa->normalize('628123456789'));      // already intl
        $this->assertSame('60123456789', $wa->normalize('60123456789'));        // Malaysia kept as-is
        $this->assertNull($wa->normalize(''));
        $this->assertNull($wa->normalize('123'));
    }

    public function test_send_posts_to_wablas_with_auth_header(): void
    {
        $this->enableWablas();
        Http::fake(['*' => Http::response(['status' => true], 200)]);

        $ok = app(WhatsAppService::class)->send('08123456789', 'Halo');

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://solo.wablas.com/api/v2/send-message'
                && $request->hasHeader('Authorization', 'test-token.secret')
                && $request['data'][0]['phone'] === '628123456789'
                && $request['data'][0]['message'] === 'Halo';
        });
    }

    public function test_send_is_noop_when_disabled(): void
    {
        config(['services.wablas.enabled' => false]);
        Http::fake();

        $this->assertFalse(app(WhatsAppService::class)->send('08123456789', 'Halo'));
        Http::assertNothingSent();
    }

    public function test_system_notification_adds_wablas_channel_only_when_enabled_and_number_present(): void
    {
        $userWithWa = $this->customer(['whatsapp' => '08123456789']);
        $userNoWa = $this->customer(['whatsapp' => null, 'phone' => null]);
        $note = new SystemNotification('Judul', 'Pesan');

        // Disabled → no WA channel.
        config(['services.wablas.enabled' => false]);
        $this->assertNotContains(WablasChannel::class, $note->via($userWithWa));

        // Enabled + number → WA channel added.
        $this->enableWablas();
        $this->assertContains(WablasChannel::class, $note->via($userWithWa));

        // Enabled but no number → no WA channel.
        $this->assertNotContains(WablasChannel::class, $note->via($userNoWa));
    }
}
