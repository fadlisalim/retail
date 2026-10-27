<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\WaCampaign;
use App\Models\WaCampaignMessage;
use App\Models\WaContact;
use App\Services\WaCampaign\WaCampaignService;
use App\Services\WaCampaign\WaCampaignSettings;
use App\Services\WaCampaign\WaContactService;
use Database\Seeders\WaCampaignIzinFollowupSeeder;
use Database\Seeders\WaCampaignIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Minta izin dulu": kontak lama dikirimi teks tanpa link/gambar → balas YA = izin promo
 * + pesan lanjutan foto & link; STOP berhenti; banyak STOP = campaign dijeda otomatis.
 */
class WaCampaignPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.campaign_mock' => false, 'services.wablas.base_url' => 'https://wablas.test', 'services.wablas.webhook_token' => '']);
        $s = app(WaCampaignSettings::class);
        $s->set('window_start', '00:00');
        $s->set('window_end', '23:59');
        $s->set('send_days', '1,2,3,4,5,6,7');
        $s->set('per_day', 500);
        $s->set('min_gap_days', 0);
        $s->set('sales_names', "EPR=Pak Eka\nFRS=Mas Faris");
        Cache::put('wa-campaign:device-check', 1, 300);
        Http::fake(['wablas.test/*' => Http::response(['status' => true, 'data' => ['messages' => [['id' => 'WB-'.uniqid(), 'status' => 'pending']]]])]);
    }

    private function legacy(string $phone, string $name, string $sales = 'EPR'): WaContact
    {
        return app(WaContactService::class)->upsert($phone, ['name' => $name, 'source' => 'ridl', 'tags' => ['legacy', 'sales-'.$sales]])->fresh();
    }

    private function permissionCampaign(?Product $product = null): WaCampaign
    {
        return WaCampaign::create([
            'name' => 'Izin Seraphim', 'status' => WaCampaign::DRAFT,
            'message' => WaCampaignIzinSeeder::MESSAGE, 'followup_message' => WaCampaignIzinSeeder::FOLLOWUP,
            'product_id' => $product?->id, 'utm_campaign' => 'izin-seraphim',
            'segment' => ['kind' => 'permission', 'tags' => ['legacy']],
        ]);
    }

    public function test_permission_message_goes_text_only_to_contacts_without_consent_and_yes_reply_opts_in_with_photo_followup(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/seraphim.jpg', 'jpg');
        $product = Product::factory()->create(['name' => 'Panel Seraphim 345Wp', 'price' => 850_000, 'main_image_path' => 'products/seraphim.jpg']);

        $hendra = $this->legacy('081268123767', 'Hendra Wijaya');
        $this->legacy('082328525988', 'Tanpa Kode', 'XYZ');
        $already = $this->legacy('083333333333', 'Sudah Izin');
        app(WaContactService::class)->optIn($already, 'checkout', 'centang checkout');
        $stop = $this->legacy('084444444444', 'Sudah Stop');
        app(WaContactService::class)->optOut($stop, 'Balas STOP');

        $campaign = $this->permissionCampaign($product);
        $service = app(WaCampaignService::class);
        $this->assertSame(2, $service->audienceCount($campaign->segment)); // hanya yang belum izin & belum STOP
        $service->schedule($campaign);
        $this->assertSame(2, $service->dispatch()['sent']);

        $m = $campaign->messages()->where('phone', '6281268123767')->first();
        $this->assertStringContainsString('Halo Kak Hendra, saya Pak Eka dari Energi.Click (Rekasurya)', $m->rendered_message);
        $this->assertStringNotContainsString('http', $m->rendered_message); // tanpa link
        $this->assertStringContainsString('saya Admin dari Energi.Click', $campaign->messages()->where('phone', '6282328525988')->value('rendered_message'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'send-image')); // tanpa gambar
        $this->assertNotNull($hendra->fresh()->permission_asked_at);
        $this->assertFalse($hendra->fresh()->isOptedIn());

        // Tidak ditanya dua kali.
        $this->assertSame(0, $service->audienceCount($campaign->segment));

        // Balas "Ya mau" → izin promo + pesan lanjutan berisi foto & link produk.
        $this->postJson('/webhook/wablas', ['id' => 'IN-1', 'phone' => '6281268123767', 'pushName' => 'Hendra', 'message' => 'Ya mau kak'])->assertOk();
        $hendra = $hendra->fresh();
        $this->assertTrue($hendra->isOptedIn());
        $this->assertSame('wa-reply', $hendra->consent_source);
        $this->assertStringContainsString('Ya mau kak', $hendra->consent_proof);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'send-image') && $r['data'][0]['phone'] === '6281268123767'
            && str_contains($r['data'][0]['caption'], 'Seraphim 345Wp') && str_contains($r['data'][0]['caption'], 'https://energi.click/s/nAe6UL')
            && ! str_contains($r['data'][0]['caption'], '/go/')); // link ditulis langsung → tidak ditambah link kedua

        // Balasan YA kedua tidak mengirim ulang (sudah izin).
        $sentBefore = count(Http::recorded());
        $this->postJson('/webhook/wablas', ['id' => 'IN-2', 'phone' => '6281268123767', 'message' => 'ya'])->assertOk();
        $this->assertSame($sentBefore, count(Http::recorded()));
    }

    public function test_yes_from_someone_never_asked_is_not_treated_as_consent(): void
    {
        $c = $this->legacy('081268123767', 'Hendra');
        $this->postJson('/webhook/wablas', ['id' => 'IN-1', 'phone' => '6281268123767', 'message' => 'Ya'])->assertOk();
        $this->assertFalse($c->fresh()->isOptedIn());

        $this->assertTrue(app(WaContactService::class)->isYesMessage('Iya boleh 👍'));
        $this->assertFalse(app(WaContactService::class)->isYesMessage('yang 200wp ada?'));
        $this->assertFalse(app(WaContactService::class)->isYesMessage('Tidak'));
    }

    public function test_promo_campaign_skips_legacy_contacts_without_yes(): void
    {
        $this->legacy('081268123767', 'Hendra');
        $promo = WaCampaign::create(['name' => 'Promo', 'status' => WaCampaign::DRAFT, 'message' => 'Halo {nama}', 'segment' => ['tags' => ['legacy']]]);
        $this->assertSame(0, app(WaCampaignService::class)->audienceCount($promo->segment));
    }

    public function test_campaign_pauses_when_too_many_recipients_reply_stop(): void
    {
        $campaign = $this->permissionCampaign();
        foreach (range(1, 40) as $i) {
            $this->legacy('0812000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Kontak '.$i);
        }
        $service = app(WaCampaignService::class);
        $service->schedule($campaign);
        app(WaCampaignSettings::class)->set('per_day', 30);
        app(WaCampaignSettings::class)->set('gap_seconds', 2); // 30 pesan per putaran dispatcher
        $service->dispatch();
        $this->assertSame(30, $campaign->messages()->whereNotNull('sent_at')->count());

        // Pesan izin: 2 STOP dari 30 (6,7%) masih wajar (batas 10%) → tidak dijeda.
        foreach (['6281200000001', '6281200000002'] as $phone) {
            $this->postJson('/webhook/wablas', ['phone' => $phone, 'message' => 'STOP'])->assertOk();
        }
        $this->assertFalse($service->pauseIfTooManyStops($campaign->fresh()));

        // 4 STOP (13,3%) → dijeda sebelum mengirim sisanya.
        foreach (['6281200000003', '6281200000004'] as $phone) {
            $this->postJson('/webhook/wablas', ['phone' => $phone, 'message' => 'STOP'])->assertOk();
        }
        app(WaCampaignSettings::class)->set('per_day', 500);
        $service->dispatch();

        $campaign = $campaign->fresh();
        $this->assertSame(WaCampaign::PAUSED, $campaign->status);
        $this->assertStringContainsString('membalas STOP', $campaign->pause_reason);
        $this->assertSame(10, $campaign->messages()->where('status', WaCampaignMessage::QUEUED)->count());

        // Admin menekan Lanjutkan → hitungan STOP dimulai ulang, sisa antrean terkirim.
        $this->travel(1)->minutes();
        $service->resume($campaign);
        $service->dispatch();
        $campaign = $campaign->fresh();
        $this->assertNotSame(WaCampaign::PAUSED, $campaign->status, (string) $campaign->pause_reason);
        $this->assertSame(0, $campaign->messages()->where('status', WaCampaignMessage::QUEUED)->count());
    }

    public function test_promo_campaign_keeps_strict_three_percent_stop_limit(): void
    {
        foreach (range(1, 30) as $i) {
            $c = $this->legacy('0813000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Promo '.$i);
            app(WaContactService::class)->optIn($c, 'checkout', 'tes');
        }
        $promo = WaCampaign::create(['name' => 'Promo', 'status' => WaCampaign::DRAFT, 'message' => 'Halo {nama}', 'segment' => ['tags' => ['legacy']]]);
        $service = app(WaCampaignService::class);
        $service->schedule($promo);
        app(WaCampaignSettings::class)->set('gap_seconds', 2);
        $service->dispatch();
        $this->postJson('/webhook/wablas', ['phone' => '6281300000001', 'message' => 'STOP'])->assertOk();
        $this->postJson('/webhook/wablas', ['phone' => '6281300000002', 'message' => 'STOP'])->assertOk();

        $this->assertTrue($service->pauseIfTooManyStops($promo->fresh()));
    }

    public function test_izin_seeder_creates_permission_draft_and_lowers_daily_quota(): void
    {
        $this->legacy('081268123767', 'Hendra');
        $this->seed(WaCampaignIzinSeeder::class);

        $c = WaCampaign::where('name', WaCampaignIzinSeeder::CAMPAIGN_NAME)->firstOrFail();
        $this->assertTrue($c->isPermission());
        $this->assertSame(WaCampaign::DRAFT, $c->status);
        $this->assertSame(1, $c->audience_count);
        $this->assertSame(40, app(WaCampaignSettings::class)->get('per_day'));
    }

    public function test_four_message_versions_rotate_evenly_with_per_version_stats_and_default_sales_name(): void
    {
        app(WaCampaignSettings::class)->set('sales_names', '*=Miftah');
        app(WaCampaignSettings::class)->set('gap_seconds', 2);
        foreach (range(1, 8) as $i) {
            $this->legacy('0812000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'Kontak'.$i, 'EPR');
        }
        $campaign = $this->permissionCampaign();
        $campaign->forceFill(['message_variants' => WaCampaignIzinSeeder::VARIANTS])->save();
        $this->assertCount(4, $campaign->variants());

        $service = app(WaCampaignService::class);
        $service->schedule($campaign);
        $service->dispatch();

        $byVariant = $campaign->messages()->selectRaw('variant, count(*) n')->groupBy('variant')->pluck('n', 'variant')->all();
        $this->assertSame([0 => 2, 1 => 2, 2 => 2, 3 => 2], array_map('intval', $byVariant)); // merata
        foreach ($campaign->messages as $m) {
            $this->assertStringContainsString('Miftah', $m->rendered_message);
            $this->assertStringContainsString('Seraphim 345Wp', $m->rendered_message);
            $this->assertStringContainsString('850rb', $m->rendered_message);
            $this->assertStringContainsString('STOP', $m->rendered_message);
        }
        $this->assertCount(4, $campaign->messages()->pluck('rendered_message')->map(fn ($t) => preg_replace('/Kontak\d/', '', $t))->unique());

        // Balas YA tercatat di statistik versinya.
        $phone = $campaign->messages()->where('variant', 2)->value('phone');
        $this->postJson('/webhook/wablas', ['phone' => $phone, 'message' => 'Ya'])->assertOk();
        $stats = collect($campaign->fresh()->variantStats())->keyBy('variant');
        $this->assertSame(1, $stats[2]['yes']);
        $this->assertSame(2, $stats[2]['sent']);
        $this->assertSame(0, $stats[0]['yes']);
    }

    public function test_followup_seeder_adds_promo_image_and_link_to_running_permission_campaign(): void
    {
        Storage::fake('public');
        $hendra = $this->legacy('081268123767', 'Hendra');
        $this->seed(WaCampaignIzinSeeder::class);
        $campaign = WaCampaign::where('name', WaCampaignIzinSeeder::CAMPAIGN_NAME)->firstOrFail();
        $service = app(WaCampaignService::class);
        $service->schedule($campaign);
        $service->dispatch();
        $this->assertFalse($campaign->fresh()->canEdit()); // sudah berjalan

        $this->seed(WaCampaignIzinFollowupSeeder::class);
        Storage::disk('public')->assertExists(WaCampaignIzinFollowupSeeder::IMAGE);

        $this->postJson('/webhook/wablas', ['phone' => '6281268123767', 'message' => 'iya'])->assertOk();
        $this->assertTrue($hendra->fresh()->isOptedIn());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'send-image')
            && str_contains($r['data'][0]['image'], 'seraphim-345-bekas-proyek.jpg')
            && str_contains($r['data'][0]['caption'], 'https://energi.click/s/nAe6UL')
            && str_contains($r['data'][0]['caption'], 'konfirmasi stok'));
    }
}
