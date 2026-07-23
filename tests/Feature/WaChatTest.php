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
}
