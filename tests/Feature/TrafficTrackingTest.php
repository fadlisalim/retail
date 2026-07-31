<?php


namespace Tests\Feature;

use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** First-touch traffic attribution + the admin traffic report. */
class TrafficTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function browse(string $uri, array $headers = []): void
    {
        $this->withHeaders(array_merge(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)'], $headers))
            ->get($uri)->assertOk();
    }

    public function test_meta_ads_visit_is_attributed_from_utm(): void
    {
        $this->browse('/?utm_source=ig&utm_medium=paid&utm_campaign=bluetti-reel&utm_content=reel-a');

        $visit = SiteVisit::first();
        $this->assertSame('meta_ads', $visit->source);
        $this->assertSame('paid', $visit->medium);
        $this->assertSame('bluetti-reel', $visit->campaign);
        $this->assertSame('reel-a', $visit->content);
        $this->assertTrue($visit->is_mobile);
        $this->assertSame('/', $visit->landing_path);
        // The raw IP is never stored.
        $this->assertNotNull($visit->ip_hash);
        $this->assertStringNotContainsString('127.0.0.1', (string) $visit->ip_hash);
    }

    public function test_organic_sources_are_detected_from_referrer(): void
    {
        $this->browse('/', ['referer' => 'https://www.google.com/search?q=panel+surya']);
        $this->assertSame('google', SiteVisit::first()->source);

        // A fresh session for the next visitor.
        $this->flushSession();
        SiteVisit::query()->delete();

        $this->browse('/', ['referer' => 'https://l.instagram.com/']);
        $this->assertSame('instagram', SiteVisit::first()->source);
    }

    /**
     * Further hits in the SAME session only bump the counter — the source
     * stays the first touch. Driven through the middleware directly because
     * the test HTTP client starts a new session per request (array driver),
     * while a real browser keeps its session cookie.
     */
    public function test_page_views_accumulate_on_one_session_row(): void
    {
        $middleware = app(\App\Http\Middleware\TrackVisit::class);
        $session = app('session.store');
        $session->setId('fixed-session-id');
        $session->start();

        $visit = function (string $uri) use ($middleware, $session) {
            $request = \Illuminate\Http\Request::create($uri, 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)']);
            $request->setLaravelSession($session);
            $middleware->handle($request, fn () => new \Illuminate\Http\Response('<html></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));
        };

        $visit('/?utm_source=ig&utm_medium=paid&utm_campaign=bluetti-reel');
        $visit('/produk');
        $visit('/faq');

        $this->assertSame(1, SiteVisit::count());
        $row = SiteVisit::first();
        $this->assertSame('meta_ads', $row->source);        // first touch kept
        $this->assertSame('bluetti-reel', $row->campaign);
        $this->assertSame(3, $row->page_views);
        $this->assertSame('/', $row->landing_path);
        $this->assertFalse($row->is_mobile);
    }

    public function test_direct_visit_is_recorded_without_referrer_or_utm(): void
    {
        $this->browse('/');

        $visit = SiteVisit::first();
        $this->assertSame('direct', $visit->source);
        $this->assertSame('none', $visit->medium);
        $this->assertNull($visit->referrer_host);
    }

    public function test_bots_admin_and_api_paths_are_not_tracked(): void
    {
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get('/')->assertOk();
        $this->get('/api/chat-toko/notif?session_id=abc')->assertOk();

        $this->assertSame(0, SiteVisit::count());
    }

    public function test_prune_command_drops_visits_older_than_retention(): void
    {
        config(['rekasurya.traffic_retention_days' => 90]);

        SiteVisit::create(['session_token' => 'old', 'source' => 'direct', 'created_at' => now()->subDays(120)]);
        SiteVisit::create(['session_token' => 'recent', 'source' => 'meta_ads', 'created_at' => now()->subDays(10)]);

        $this->artisan('visits:prune')->assertSuccessful();

        $this->assertSame(1, SiteVisit::count());
        $this->assertSame('meta_ads', SiteVisit::first()->source);
    }

    public function test_admin_traffic_report_shows_sources_and_campaigns(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        SiteVisit::create(['session_token' => 'a', 'source' => 'meta_ads', 'medium' => 'paid', 'campaign' => 'bluetti-reel', 'landing_path' => '/kategori/portable-power', 'created_at' => now()]);
        SiteVisit::create(['session_token' => 'b', 'source' => 'google', 'medium' => 'organic', 'landing_path' => '/', 'created_at' => now()]);

        $this->actingAs($admin)->get('/admin/trafik')
            ->assertOk()
            ->assertSee('Meta Ads (FB/IG berbayar)')
            ->assertSee('Google (pencarian)')
            ->assertSee('bluetti-reel')
            ->assertSee('/kategori/portable-power');
    }

    public function test_traffic_report_requires_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]); // no role

        $this->actingAs($user)->get('/admin/trafik')->assertForbidden();
    }
}
