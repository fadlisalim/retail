<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WaMessage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WaChatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        return $admin;
    }

    public function test_webhook_stores_incoming_message_and_dedupes(): void
    {
        $payload = ['id' => 'WB-1', 'phone' => '08170188989', 'pushName' => 'Sandi', 'message' => 'Halo, panelnya ready?', 'isGroup' => false];

        $this->postJson('/webhook/wablas', $payload)->assertOk();
        $this->postJson('/webhook/wablas', $payload)->assertOk(); // retry → dedupe

        $this->assertSame(1, WaMessage::count());
        $m = WaMessage::first();
        $this->assertSame('628170188989', $m->phone); // normalised 08 → 628
        $this->assertSame('in', $m->direction);
        $this->assertSame('Sandi', $m->name);
        $this->assertFalse($m->is_read);
    }

    public function test_webhook_rejects_wrong_token_when_configured(): void
    {
        config(['services.wablas.webhook_token' => 'rahasia']);

        $this->postJson('/webhook/wablas', ['phone' => '0812', 'message' => 'x'])->assertForbidden();
        $this->postJson('/webhook/wablas?token=salah', ['phone' => '0812', 'message' => 'x'])->assertForbidden();
        $this->postJson('/webhook/wablas?token=rahasia', ['phone' => '081234567890', 'message' => 'ok'])->assertOk();

        $this->assertSame(1, WaMessage::count());
    }

    public function test_webhook_records_from_me_messages_as_outgoing(): void
    {
        // Replies typed on the device phone arrive with fromMe=true — they must
        // render on the right (outgoing), not as a customer bubble.
        $this->postJson('/webhook/wablas', ['id' => 'WB-9', 'phone' => '08170188989', 'message' => 'Siap pak, saya cek dulu', 'fromMe' => true])->assertOk();

        $m = WaMessage::first();
        $this->assertSame('out', $m->direction);
        $this->assertTrue($m->is_read);   // no unread badge for our own replies
        $this->assertTrue($m->sent_ok);
    }

    public function test_webhook_ignores_group_messages(): void
    {
        $this->postJson('/webhook/wablas', ['phone' => '081234567890', 'message' => 'hi', 'isGroup' => true])->assertOk();
        $this->assertSame(0, WaMessage::count());
    }

    public function test_admin_can_send_reply_via_wablas(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://console.wablas.com']);
        Http::fake(['console.wablas.com/*' => Http::response(['status' => true], 200)]);

        $this->actingAs($this->admin())
            ->postJson('/admin/wa-chat/kirim', ['phone' => '08170188989', 'message' => 'Siap kak, ready!'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $m = WaMessage::first();
        $this->assertSame('out', $m->direction);
        $this->assertSame('628170188989', $m->phone);
        $this->assertTrue($m->sent_ok);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v2/send-message'));
    }

    public function test_inbox_page_lists_conversations_and_thread(): void
    {
        WaMessage::create(['phone' => '628170188989', 'name' => 'Sandi', 'direction' => 'in', 'message' => 'Halo, panelnya ready?', 'is_read' => false, 'created_at' => now()]);

        $this->actingAs($this->admin())
            ->get('/admin/wa-chat?phone=628170188989')
            ->assertOk()
            ->assertSee('Sandi')
            ->assertSee('Halo, panelnya ready?');

        // Opening the thread marks it read.
        $this->assertTrue(WaMessage::first()->is_read);
    }

    public function test_staff_without_permission_cannot_open_inbox(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', 'admin-gudang')->first());

        $this->actingAs($staff)->get('/admin/wa-chat')->assertForbidden();
    }

    public function test_webhook_stores_incoming_media_as_media_not_text(): void
    {
        // Some Wablas servers post a full URL...
        $this->postJson('/webhook/wablas', [
            'id' => 'WB-IMG-1', 'phone' => '08170188989', 'pushName' => 'Sandi',
            'message' => 'ini fotonya', 'messageType' => 'image',
            'file' => 'https://pati.wablas.com/media/2TNEV6.jpeg',
        ])->assertOk();

        $m = WaMessage::first();
        $this->assertSame('ini fotonya', $m->message);              // caption stays text
        $this->assertSame('https://pati.wablas.com/media/2TNEV6.jpeg', $m->media_url);
        $this->assertSame('image', $m->media_type);
        $this->assertSame('2TNEV6.jpeg', $m->media_name);
        $this->assertTrue($m->isImage());
        $this->assertStringNotContainsString('[media]', (string) $m->message);
    }

    public function test_bare_media_filename_is_resolved_with_the_configured_base_url(): void
    {
        config(['services.wablas.media_base_url' => 'https://pati.wablas.com/media/']);

        $this->postJson('/webhook/wablas', [
            'id' => 'WB-IMG-2', 'phone' => '08170188989',
            'file' => '2TNEV6-3A20A39D842FB54E2E84.jpeg', 'messageType' => 'image',
        ])->assertOk();

        $m = WaMessage::first();
        $this->assertSame('https://pati.wablas.com/media/2TNEV6-3A20A39D842FB54E2E84.jpeg', $m->media_url);
        $this->assertSame('image', $m->media_type);
        $this->assertSame('', (string) $m->message);
    }

    public function test_media_type_falls_back_to_the_file_extension(): void
    {
        $this->postJson('/webhook/wablas', [
            'id' => 'WB-DOC-1', 'phone' => '08170188989',
            'file' => 'https://pati.wablas.com/media/invoice.pdf',
        ])->assertOk();

        $m = WaMessage::first();
        $this->assertSame('document', $m->media_type);
        $this->assertFalse($m->isImage());
    }

    public function test_admin_can_send_an_image_attachment(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        Http::fake(['pati.wablas.com/*' => Http::response(['status' => true], 200)]);

        $this->actingAs($this->admin())->post('/admin/wa-chat/kirim-media', [
            'phone' => '08170188989',
            'caption' => 'Foto unit',
            'file' => \Illuminate\Http\UploadedFile::fake()->image('unit.jpg'),
        ])->assertOk()->assertJsonPath('ok', true);

        $m = WaMessage::first();
        $this->assertSame('out', $m->direction);
        $this->assertSame('image', $m->media_type);
        $this->assertSame('unit.jpg', $m->media_name);
        $this->assertSame('Foto unit', $m->message);
        $this->assertTrue($m->sent_ok);

        // Wablas is told to fetch the file from our public URL.
        Http::assertSent(function ($req) {
            $row = $req['data'][0] ?? [];

            return str_contains((string) $req->url(), 'send-image')
                && str_starts_with((string) ($row['image'] ?? ''), 'http')
                && ($row['caption'] ?? null) === 'Foto unit';
        });
    }

    public function test_pdf_attachment_is_sent_as_a_document(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        Http::fake(['pati.wablas.com/*' => Http::response(['status' => true], 200)]);

        $this->actingAs($this->admin())->post('/admin/wa-chat/kirim-media', [
            'phone' => '08170188989',
            'file' => \Illuminate\Http\UploadedFile::fake()->create('penawaran.pdf', 120, 'application/pdf'),
        ])->assertOk();

        $this->assertSame('document', WaMessage::first()->media_type);
        Http::assertSent(fn ($req) => str_contains((string) $req->url(), 'send-document'));
    }

    public function test_backfill_converts_old_media_text_rows(): void
    {
        config(['services.wablas.media_base_url' => 'https://pati.wablas.com/media/']);

        WaMessage::create(['phone' => '628112231107', 'direction' => 'in', 'message' => '[media] 2TNEV6-AC8E0.pptx', 'created_at' => now()]);
        WaMessage::create(['phone' => '628112231107', 'direction' => 'in', 'message' => '[media] 2TNEV6-AC1FF.f4v', 'created_at' => now()]);
        WaMessage::create(['phone' => '628112231107', 'direction' => 'in', 'message' => 'pesan teks biasa', 'created_at' => now()]);

        $this->artisan('wa:backfill-media')->assertSuccessful();

        $doc = WaMessage::where('media_name', '2TNEV6-AC8E0.pptx')->first();
        $this->assertSame('https://pati.wablas.com/media/2TNEV6-AC8E0.pptx', $doc->media_url);
        $this->assertSame('document', $doc->media_type);
        $this->assertSame('', $doc->message);

        $this->assertSame('video', WaMessage::where('media_name', '2TNEV6-AC1FF.f4v')->first()->media_type);
        // Plain text rows are left alone.
        $this->assertNull(WaMessage::where('message', 'pesan teks biasa')->first()->media_url);
    }
}
