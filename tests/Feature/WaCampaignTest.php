<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WaCampaign;
use App\Models\WaCampaignMessage;
use App\Models\WaContact;
use App\Services\WaCampaign\WaCampaignService;
use App\Services\WaCampaign\WaCampaignSettings;
use App\Services\WaCampaign\WaContactService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * WhatsApp Campaign: izin promo wajib, antrean persisten tanpa duplikat, dispatcher
 * terkendali (jam kirim, rate, kuota, 7 hari), STOP via webhook, timeout = tidak
 * pasti tanpa kirim ulang, jeda otomatis saat gagal beruntun, import CSV, akses.
 */
class WaCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Live transport di-fake; mock mode dimatikan supaya jalur HTTP teruji. Jam kirim selalu terbuka.
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.campaign_mock' => false, 'services.wablas.base_url' => 'https://wablas.test', 'services.wablas.webhook_token' => '']);
        $s = app(WaCampaignSettings::class);
        $s->set('window_start', '00:00');
        $s->set('window_end', '23:59');
        $s->set('send_days', '1,2,3,4,5,6,7');
        // Cek device (GET /api/device/info) dianggap sudah dilakukan, kecuali tes yang mengujinya.
        Cache::put('wa-campaign:device-check', 1, 300);
    }

    private function staff(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['is_staff' => true, 'is_active' => true, 'name' => 'Admin '.$role]);
        $u->roles()->attach(Role::where('slug', $role)->first());

        return $u;
    }

    private function contact(string $phone, string $name, bool $optIn = true, array $extra = []): WaContact
    {
        $c = app(WaContactService::class)->upsert($phone, ['name' => $name, 'source' => 'manual'] + $extra);
        if ($optIn) {
            app(WaContactService::class)->optIn($c, 'manual', 'tes');
        }

        return $c->fresh();
    }

    private function campaign(array $attrs = []): WaCampaign
    {
        return WaCampaign::create(array_merge(['name' => 'Promo Oktober', 'message' => 'Halo {nama}, promo panel surya!', 'status' => WaCampaign::DRAFT, 'segment' => []], $attrs));
    }

    private function fakeWablasOk(): void
    {
        Http::fake(['wablas.test/*' => Http::response(['status' => true, 'data' => ['messages' => [['id' => 'WB-'.uniqid(), 'status' => 'pending']]]])]);
    }

    public function test_only_opted_in_contacts_receive_and_personalisation_with_utm_and_stop_footer(): void
    {
        $this->fakeWablasOk();
        $this->contact('081111111111', 'Budi Santoso');            // izin
        $this->contact('082222222222', 'Tanpa Izin', optIn: false);
        $out = $this->contact('083333333333', 'Sudah Stop');
        app(WaContactService::class)->optOut($out, 'Balas STOP');

        $campaign = $this->campaign(['link_url' => 'https://energi.click/barang-clearance', 'utm_campaign' => 'promo-okt']);
        app(WaCampaignService::class)->schedule($campaign);
        $this->assertSame(1, $campaign->messages()->count()); // hanya kontak ber-izin yang masuk antrean
        $this->assertSame(WaCampaign::RUNNING, $campaign->fresh()->status);

        $r = app(WaCampaignService::class)->dispatch();
        $this->assertSame(1, $r['sent']);

        $m = $campaign->messages()->first();
        $this->assertSame(WaCampaignMessage::ACCEPTED, $m->status);
        $this->assertStringStartsWith('WB-', $m->wablas_id);
        $this->assertStringContainsString('Halo Budi,', $m->rendered_message);
        $this->assertStringContainsString('utm_source=whatsapp&utm_medium=campaign&utm_campaign=promo-okt', $m->rendered_message);
        $this->assertStringContainsString('Balas STOP untuk berhenti promo', $m->rendered_message);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/v2/send-message') && $req->header('Authorization')[0] === 'tok' && $req['data'][0]['phone'] === '6281111111111');
        $this->assertSame(WaCampaign::COMPLETED, $campaign->fresh()->status);
        $this->assertNotNull(WaContact::where('phone', '6281111111111')->value('last_promo_at'));

        // Menjadwalkan ulang campaign yang sama tidak membuat baris ganda (unique campaign+kontak).
        app(WaCampaignService::class)->schedule($campaign);
        $this->assertSame(1, $campaign->messages()->count());
    }

    public function test_seven_day_gap_across_campaigns_and_consent_rechecked_right_before_dispatch(): void
    {
        $this->fakeWablasOk();
        $a = $this->contact('081111111111', 'Budi');
        $b = $this->contact('082222222222', 'Citra');
        $a->forceFill(['last_promo_at' => now()->subDays(3)])->save(); // baru dapat promo dari campaign lain

        $campaign = $this->campaign();
        app(WaCampaignService::class)->schedule($campaign);
        $this->assertSame(2, $campaign->messages()->count());

        // Citra membalas STOP setelah campaign dimulai, sebelum gilirannya dikirim.
        app(WaContactService::class)->optOut($b, 'Balas STOP');

        $r = app(WaCampaignService::class)->dispatch();
        $this->assertSame(0, $r['sent']);
        $this->assertSame('Sudah menerima promo < 7 hari', $campaign->messages()->where('contact_id', $a->id)->value('error'));
        $this->assertSame(WaCampaignMessage::CANCELLED, $campaign->messages()->where('contact_id', $b->id)->value('status'));
        Http::assertNothingSent();
    }

    public function test_stop_via_webhook_opts_out_cancels_queue_and_import_never_revives(): void
    {
        $this->fakeWablasOk();
        $c = $this->contact('081111111111', 'Budi');
        $campaign = $this->campaign();
        app(WaCampaignService::class)->schedule($campaign);

        $this->postJson('/webhook/wablas', ['id' => 'IN-1', 'phone' => '081111111111', 'pushName' => 'Budi', 'message' => 'STOP'])->assertOk();
        $this->postJson('/webhook/wablas', ['id' => 'IN-1', 'phone' => '081111111111', 'pushName' => 'Budi', 'message' => 'STOP'])->assertOk(); // retry duplikat aman

        $c->refresh();
        $this->assertTrue($c->isOptedOut());
        $this->assertSame('Balas STOP', $c->opted_out_reason);
        $this->assertSame(WaCampaignMessage::CANCELLED, $campaign->messages()->first()->status);
        // Konfirmasi berhenti dikirim sekali (bukan dua kali meski webhook diulang).
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req['data'][0]['message'], 'tidak akan menerima promo'));

        // Import ulang dengan izin=ya TIDAK menghidupkan kontak yang STOP.
        $n = app(WaContactService::class)->importCsv("telepon,nama,izin,bukti\n081111111111,Budi,ya,form event\n0899000000,Dewi,ya,form event\n", 'import');
        $this->assertSame(1, $n['kept_out']);
        $this->assertSame(1, $n['opted_in']);
        $this->assertTrue($c->fresh()->isOptedOut());
        $this->assertTrue(WaContact::where('phone', '62899000000')->first()->isOptedIn());

        // Sinkron data toko juga tidak mengubah izin.
        app(WaContactService::class)->syncFromExisting();
        $this->assertTrue($c->fresh()->isOptedOut());
    }

    public function test_timeout_marks_uncertain_without_automatic_resend_and_status_webhook_updates_delivery(): void
    {
        $this->contact('081111111111', 'Budi');
        $campaign = $this->campaign();
        app(WaCampaignService::class)->schedule($campaign);

        // Request pertama timeout (hasil tidak diketahui), request berikutnya sukses.
        $calls = 0;
        Http::fake(['wablas.test/*' => function ($req) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
            }

            return Http::response(['status' => true, 'data' => ['messages' => [['id' => 'WB-77', 'status' => 'pending']]]]);
        }]);
        $r = app(WaCampaignService::class)->dispatch();
        $this->assertSame(1, $r['uncertain']);
        $m = $campaign->messages()->first();
        $this->assertSame(WaCampaignMessage::UNCERTAIN, $m->status);
        $this->assertNull($m->next_attempt_at);

        // Putaran berikutnya TIDAK mengirim ulang pesan yang tidak pasti.
        app(WaCampaignService::class)->dispatch();
        $this->assertSame(1, $calls);
        $this->assertSame(1, $m->fresh()->attempts);

        // Admin cek Wablas → antre ulang manual, lalu terkirim; webhook status memajukan ke delivered/read (idempotent, tidak mundur).
        $admin = $this->staff('super-admin');
        $this->actingAs($admin)->post(route('admin.wa-campaign.requeue', [$campaign, $m]))->assertRedirect()->assertSessionHas('success');
        app(WaCampaignSettings::class)->set('min_gap_days', 0);
        app(WaCampaignService::class)->dispatch();
        $this->assertSame(WaCampaignMessage::ACCEPTED, $m->fresh()->status);

        $this->postJson('/webhook/wablas', ['id' => 'WB-77', 'phone' => '6281111111111', 'status' => 'read'])->assertOk();
        $this->postJson('/webhook/wablas', ['id' => 'WB-77', 'phone' => '6281111111111', 'status' => 'delivered'])->assertOk(); // terlambat → diabaikan
        $this->assertSame(WaCampaignMessage::READ, $m->fresh()->status);
        $this->assertNotNull($m->fresh()->delivered_at);

        // Balasan dari penerima dalam 7 hari dihitung.
        $this->postJson('/webhook/wablas', ['id' => 'IN-9', 'phone' => '081111111111', 'pushName' => 'Budi', 'message' => 'Masih ada stok?'])->assertOk();
        $this->assertSame(1, $m->fresh()->reply_count);
        $this->assertSame(1, $campaign->fresh()->stats()['replied']);
    }

    public function test_consecutive_failures_pause_the_campaign_and_retryable_errors_backoff(): void
    {
        app(WaCampaignSettings::class)->set('failure_pause_after', 2);
        app(WaCampaignSettings::class)->set('per_minute', 10);
        foreach (['081111111111', '082222222222', '083333333333'] as $i => $p) {
            $this->contact($p, 'Kontak '.$i);
        }
        $campaign = $this->campaign();
        app(WaCampaignService::class)->schedule($campaign);

        Http::fake(['wablas.test/*' => Http::sequence()
            ->push(['status' => false, 'message' => 'server busy'], 503)
            ->push(['status' => false, 'message' => 'server busy'], 503)
            ->push(['status' => false, 'message' => 'invalid token'], 401)]);
        $r = app(WaCampaignService::class)->dispatch();
        $this->assertSame(2, $r['failed']);
        $campaign->refresh();
        $this->assertSame(WaCampaign::PAUSED, $campaign->status);
        $this->assertStringContainsString('2 kegagalan beruntun', $campaign->pause_reason);
        $failed = $campaign->messages()->where('status', WaCampaignMessage::FAILED)->get();
        $this->assertCount(2, $failed);
        $this->assertNotNull($failed->first()->next_attempt_at); // 503 = sementara → retry dengan backoff
        $this->assertSame(1, $campaign->messages()->where('status', WaCampaignMessage::QUEUED)->count()); // sisanya tidak disentuh

        // Token ditolak = tidak retry (respons ke-3 dari sequence: 401).
        app(WaCampaignService::class)->resume($campaign);
        app(WaCampaignService::class)->dispatch();
        $m = $campaign->messages()->where('phone', '6283333333333')->first();
        $this->assertSame(WaCampaignMessage::FAILED, $m->status);
        $this->assertNull($m->next_attempt_at);
        $this->assertStringContainsString('Token Wablas ditolak', $m->error);
    }

    public function test_window_daily_limit_emergency_stop_and_device_disconnect_block_sending(): void
    {
        $device = 'connected';
        Http::fake(['wablas.test/*' => function ($req) use (&$device) {
            if (str_contains($req->url(), 'device/info')) {
                return Http::response(['status' => true, 'data' => ['status' => $device]]);
            }

            return Http::response(['status' => true, 'data' => ['messages' => [['id' => 'WB-1', 'status' => 'pending']]]]);
        }]);
        $this->contact('081111111111', 'Budi');
        $campaign = $this->campaign();
        $service = app(WaCampaignService::class);
        $settings = app(WaCampaignSettings::class);
        $service->schedule($campaign);

        $settings->set('window_start', '09:00');
        $settings->set('window_end', '09:01');
        $this->assertSame('outside_window', $service->dispatch()['reason']);
        $settings->set('window_start', '00:00');
        $settings->set('window_end', '23:59');

        $service->emergencyStop(true, 'Tester');
        $this->assertSame('emergency_stop', $service->dispatch()['reason']);
        $this->assertSame(WaCampaign::PAUSED, $campaign->fresh()->status);
        $service->emergencyStop(false, 'Tester');
        $campaign = $campaign->fresh();
        $service->resume($campaign);
        $this->assertSame(WaCampaign::RUNNING, $campaign->fresh()->status);

        $settings->set('per_day', 0);
        $this->assertSame('daily_limit', $service->dispatch()['reason']);
        $settings->set('per_day', 100);

        // Device putus → campaign berjalan dijeda, tidak ada pesan keluar.
        Cache::forget('wa-campaign:device-check');
        $device = 'disconnected';
        $this->assertSame('device_disconnected', $service->dispatch()['reason']);
        $this->assertStringContainsString('tidak terhubung', $campaign->fresh()->pause_reason);
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'send-message'));
        $this->assertSame(0, WaCampaignMessage::where('status', '!=', WaCampaignMessage::QUEUED)->count());
    }

    public function test_mock_mode_never_calls_wablas(): void
    {
        config(['services.wablas.campaign_mock' => true]);
        Http::fake();
        $this->contact('081111111111', 'Budi');
        $campaign = $this->campaign();
        app(WaCampaignService::class)->schedule($campaign);
        $r = app(WaCampaignService::class)->dispatch();
        $this->assertSame(1, $r['sent']);
        $this->assertStringStartsWith('MOCK-', $campaign->messages()->first()->wablas_id);
        Http::assertNothingSent();
    }

    public function test_admin_ui_create_start_contacts_import_and_access_control(): void
    {
        Storage::fake('public');
        $this->fakeWablasOk();
        $sales = $this->staff('admin-sales');
        $gudang = $this->staff('admin-gudang');
        $this->contact('081111111111', 'Budi', extra: ['tags' => ['reseller']]);
        $this->contact('082222222222', 'Citra');

        $this->actingAs($gudang)->get(route('admin.wa-campaign.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.wa-campaign.index'))->assertOk()->assertSee('WhatsApp Campaign')->assertSee('Kontak ber-izin');
        $this->actingAs($sales)->get(route('admin.dashboard'))->assertSee('WA Campaign');

        // Hitung audiens: filter tag mempersempit ke kontak ber-izin yang cocok.
        $this->actingAs($sales)->postJson(route('admin.wa-campaign.audience'), ['tags' => ['reseller']])->assertOk()->assertJson(['count' => 1, 'eligible_total' => 2]);

        // Buat + mulai dari builder, dengan gambar upload dan simpan template.
        $this->actingAs($sales)->post(route('admin.wa-campaign.store'), [
            'name' => 'Promo Reseller', 'message' => 'Halo {nama}, harga khusus reseller.', 'tags' => ['reseller'],
            'image' => UploadedFile::fake()->image('promo.jpg', 600, 600), 'save_template' => 1, 'template_name' => 'Reseller',
            'action' => 'start',
        ])->assertRedirect()->assertSessionHas('success');
        $campaign = WaCampaign::firstOrFail();
        $this->assertSame(WaCampaign::RUNNING, $campaign->status);
        $this->assertSame(1, $campaign->messages()->count());
        $this->assertNotNull($campaign->image_path);
        $this->assertDatabaseHas('wa_campaign_templates', ['name' => 'Reseller']);

        $this->actingAs($sales)->get(route('admin.wa-campaign.show', $campaign))->assertOk()->assertSee('Promo Reseller')->assertSee('Halo Budi,')->assertSee('Antre');
        $this->actingAs($sales)->get(route('admin.wa-campaign.edit', $campaign))->assertForbidden(); // sudah berjalan

        // Dispatch kirim gambar + caption ke endpoint send-image.
        app(WaCampaignService::class)->dispatch();
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/v2/send-image') && str_contains($req['data'][0]['caption'], 'Halo Budi'));

        // Jeda / lanjut / batal.
        $c2 = $this->campaign(['name' => 'Kedua']);
        app(WaCampaignService::class)->schedule($c2);
        $this->actingAs($sales)->post(route('admin.wa-campaign.pause', $c2))->assertRedirect();
        $this->assertSame(WaCampaign::PAUSED, $c2->fresh()->status);
        $this->actingAs($sales)->post(route('admin.wa-campaign.resume', $c2))->assertRedirect();
        $this->assertSame(WaCampaign::RUNNING, $c2->fresh()->status);
        $this->actingAs($sales)->post(route('admin.wa-campaign.cancel', $c2))->assertRedirect();
        $this->assertSame(WaCampaign::CANCELLED, $c2->fresh()->status);
        $this->assertSame(0, $c2->messages()->where('status', WaCampaignMessage::QUEUED)->count());

        // Kontak: import CSV lewat UI, tambah manual tanpa bukti ditolak izin, nomor invalid ditolak.
        $csv = UploadedFile::fake()->createWithContent('kontak.csv', "nomor;nama;tag;izin;bukti\n0812-3456-7890;Dewi;event;ya;form pameran\n+62 857 1111 2222;Eko;;tidak;\nabc;Salah;;ya;\n");
        $this->actingAs($sales)->post(route('admin.wa-campaign.contacts.import'), ['file' => $csv])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(WaContact::where('phone', '6281234567890')->first()->isOptedIn());
        $this->assertSame('unknown', WaContact::where('phone', '6285711112222')->value('consent_status'));
        $this->assertNull(WaContact::where('name', 'Salah')->first());
        $this->actingAs($sales)->post(route('admin.wa-campaign.contacts.store'), ['phone' => '0813', 'name' => 'Pendek'])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($sales)->post(route('admin.wa-campaign.contacts.store'), ['phone' => '081399998888', 'name' => 'Fajar', 'consent' => 1])->assertSessionHasErrors('proof');
        $this->actingAs($sales)->get(route('admin.wa-campaign.contacts', ['consent' => 'opted_in']))->assertOk()->assertSee('Dewi')->assertDontSee('Eko');

        // Pengaturan: footer tanpa STOP ditolak; nomor tes disimpan ternormalisasi.
        $this->actingAs($sales)->put(route('admin.wa-campaign.settings.update'), ['window_start' => '09:00', 'window_end' => '18:00', 'timezone' => 'Asia/Jakarta', 'per_minute' => 4, 'per_day' => 200, 'min_gap_days' => 7, 'failure_pause_after' => 5, 'max_attempts' => 3, 'footer' => 'Balas BERHENTI', 'connection_type' => 'qr'])
            ->assertSessionHasErrors('footer');
        $this->actingAs($sales)->put(route('admin.wa-campaign.settings.update'), ['window_start' => '09:00', 'window_end' => '18:00', 'timezone' => 'Asia/Jakarta', 'per_minute' => 4, 'per_day' => 200, 'min_gap_days' => 7, 'failure_pause_after' => 5, 'max_attempts' => 3, 'footer' => 'Balas STOP untuk berhenti promo.', 'connection_type' => 'qr', 'test_phone' => '0812 9999 0000'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('6281299990000', app(WaCampaignSettings::class)->get('test_phone'));

        // Kirim tes hanya ke nomor tes, tidak menyentuh antrean/kontak.
        $this->actingAs($sales)->postJson(route('admin.wa-campaign.test'), ['name' => 'Tes', 'message' => 'Halo {nama}, tes.'])->assertOk();
        Http::assertSent(fn ($req) => str_contains($req->url(), 'send-message') && $req['data'][0]['phone'] === '6281299990000' && str_contains($req['data'][0]['message'], 'Halo Tes,'));
        $this->assertSame(0, WaCampaignMessage::where('phone', '6281299990000')->count());
    }

    public function test_checkout_and_register_consent_checkbox_records_opt_in_with_proof(): void
    {
        $this->post('/daftar', ['name' => 'Rina Promo', 'email' => 'rina@test.id', 'whatsapp' => '081377776666', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123', 'promo_consent' => 1])->assertRedirect();
        $c = WaContact::where('phone', '6281377776666')->firstOrFail();
        $this->assertTrue($c->isOptedIn());
        $this->assertSame('register', $c->consent_source);
        $this->assertStringContainsString('saat daftar akun', $c->consent_proof);
        $this->assertNotNull($c->user_id);

        // Tanpa centang → tidak ada izin.
        $this->post('/daftar', ['name' => 'Tanpa Izin', 'email' => 'no@test.id', 'whatsapp' => '081366665555', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123'])->assertRedirect();
        $this->assertNull(WaContact::where('phone', '6281366665555')->first());
    }

    public function test_builder_template_and_settings_pages_render(): void
    {
        $sales = $this->staff('admin-sales');
        $this->actingAs($sales)->get(route('admin.wa-campaign.create'))->assertOk()->assertSee('Preview WhatsApp')->assertSee('Kirim tes');
        $this->actingAs($sales)->post(route('admin.wa-campaign.templates.store'), ['name' => 'Salam', 'body' => 'Halo {nama}'])->assertRedirect();
        $this->actingAs($sales)->get(route('admin.wa-campaign.templates'))->assertOk()->assertSee('Salam');
        $this->actingAs($sales)->get(route('admin.wa-campaign.settings'))->assertOk()->assertSee('Jenis koneksi akun Wablas')->assertSee('/webhook/wablas');
    }

    public function test_dispatch_command_runs_and_reports(): void
    {
        $this->fakeWablasOk();
        $this->contact('081111111111', 'Budi');
        $campaign = $this->campaign();
        app(WaCampaignService::class)->schedule($campaign);
        $this->artisan('wa-campaign:dispatch')->expectsOutputToContain('terkirim 1')->assertSuccessful();
        $this->artisan('wa-campaign:sync-contacts')->expectsOutputToContain('Izin promo tidak diubah')->assertSuccessful();
    }
}
