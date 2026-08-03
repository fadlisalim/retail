<?php

namespace Tests\Feature;

use App\Models\AssistantLead;
use App\Models\Product;
use App\Models\SiteChatMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** On-site "Chat Toko": customer ↔ admin chat (not the AI assistant). */
class SiteChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_leave_name_and_phone_before_chatting(): void
    {
        $res = $this->postJson('/api/chat-toko/kirim', [
            'session_id' => 'sess-shop-1',
            'message' => 'Barang ini ready?',
        ]);

        $res->assertStatus(422)->assertJsonPath('need_contact', true);
        $this->assertSame(0, SiteChatMessage::count());
    }

    public function test_guest_first_message_with_contact_creates_lead_and_message(): void
    {
        $p = Product::factory()->create(['name' => 'Panel Surya Uji 550Wp', 'status' => 'published', 'price' => 2000000, 'stock' => 4]);

        $res = $this->postJson('/api/chat-toko/kirim', [
            'session_id' => 'sess-shop-2',
            'message' => 'Barang ini ready kak?',
            'product_slug' => $p->slug,
            'name' => 'Budi',
            'phone' => '081234567890',
        ])->assertOk()->assertJsonPath('ok', true);

        $msg = SiteChatMessage::first();
        $this->assertSame('in', $msg->direction);
        $this->assertSame($p->slug, $msg->product_slug);

        $lead = AssistantLead::where('session_id', 'sess-shop-2')->first();
        $this->assertSame('Budi', $lead->name);
        $this->assertSame('6281234567890', $lead->phone);

        // Follow-up messages need no contact data anymore.
        $this->postJson('/api/chat-toko/kirim', ['session_id' => 'sess-shop-2', 'message' => 'Halo?'])
            ->assertOk()->assertJsonPath('ok', true);
    }

    public function test_logged_in_customer_chats_without_contact_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/chat-toko/kirim', [
            'session_id' => 'sess-shop-3',
            'message' => 'Halo admin',
        ])->assertOk();

        $this->assertSame($user->id, SiteChatMessage::first()->user_id);
    }

    public function test_customer_poll_returns_admin_reply_with_product_card(): void
    {
        $p = Product::factory()->create(['name' => 'Inverter Uji 3000W', 'status' => 'published', 'price' => 5000000, 'stock' => 2]);
        AssistantLead::create(['session_id' => 'sess-shop-4', 'name' => 'Sari', 'phone' => '628111']);

        SiteChatMessage::create(['session_id' => 'sess-shop-4', 'direction' => 'in', 'message' => 'Ready?', 'product_slug' => $p->slug, 'created_at' => now()]);
        SiteChatMessage::create(['session_id' => 'sess-shop-4', 'direction' => 'out', 'message' => 'Ready Kak, silakan diorder 😊', 'created_at' => now()]);

        $res = $this->getJson('/api/chat-toko/pesan?session_id=sess-shop-4&after_id=0')->assertOk();

        $this->assertCount(2, $res->json('messages'));
        $res->assertJsonPath('messages.0.product.name', 'Inverter Uji 3000W')
            ->assertJsonPath('messages.1.direction', 'out')
            ->assertJsonPath('need_contact', false);

        // Delivered admin replies are marked read for the badge/bookkeeping.
        $this->assertTrue(SiteChatMessage::where('direction', 'out')->first()->is_read);
    }

    public function test_admin_inbox_lists_reply_and_marks_read(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(\App\Models\Role::where('slug', 'super-admin')->first());

        AssistantLead::create(['session_id' => 'sess-shop-5', 'name' => 'Dodi', 'phone' => '628222']);
        SiteChatMessage::create(['session_id' => 'sess-shop-5', 'direction' => 'in', 'message' => 'Bisa nego?', 'created_at' => now()]);

        // Inbox page shows the conversation (guest name via lead).
        $this->actingAs($admin)->get('/admin/chat-toko')->assertOk()->assertSee('Dodi');

        // Open the thread → incoming marked read.
        $this->actingAs($admin)->get('/admin/chat-toko?sesi=sess-shop-5')->assertOk()->assertSee('Bisa nego?');
        $this->assertTrue(SiteChatMessage::first()->is_read);

        // Reply lands as 'out' with the admin id, customer will poll it.
        $this->actingAs($admin)->postJson('/admin/chat-toko/kirim', ['sesi' => 'sess-shop-5', 'message' => 'Harga sudah nett Kak 🙏'])
            ->assertOk()->assertJsonPath('ok', true);
        $reply = SiteChatMessage::where('direction', 'out')->first();
        $this->assertSame($admin->id, $reply->admin_id);
    }

    public function test_unread_bell_counts_only_undelivered_admin_replies(): void
    {
        SiteChatMessage::create(['session_id' => 'sess-shop-6', 'direction' => 'out', 'message' => 'Halo Kak', 'created_at' => now()]);
        SiteChatMessage::create(['session_id' => 'sess-shop-6', 'direction' => 'out', 'message' => 'Stok ready ya', 'created_at' => now()]);
        SiteChatMessage::create(['session_id' => 'sess-shop-6', 'direction' => 'in', 'message' => 'Oke', 'created_at' => now()]); // own msg — not counted
        SiteChatMessage::create(['session_id' => 'sess-other', 'direction' => 'out', 'message' => 'Lain sesi', 'created_at' => now()]);

        $this->getJson('/api/chat-toko/notif?session_id=sess-shop-6')
            ->assertOk()->assertJsonPath('unread', 2);

        // Loading the conversation (widget open) delivers them → badge drops to 0.
        $this->getJson('/api/chat-toko/pesan?session_id=sess-shop-6&after_id=0')->assertOk();
        $this->getJson('/api/chat-toko/notif?session_id=sess-shop-6')
            ->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_new_web_chat_pings_the_admin_wa_once_per_burst(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        app(\App\Services\SettingService::class)->set('whatsapp.admin_notify', '628999888777', 'string', 'whatsapp');
        \Illuminate\Support\Facades\Http::fake(['pati.wablas.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);

        AssistantLead::create(['session_id' => 'sess-shop-7', 'name' => 'Rina', 'phone' => '628333']);

        $this->postJson('/api/chat-toko/kirim', ['session_id' => 'sess-shop-7', 'message' => 'Ready kak?'])->assertOk();

        \Illuminate\Support\Facades\Http::assertSent(function ($req) {
            $data = $req['data'][0] ?? [];

            return str_contains((string) $req->url(), 'send-message')
                && $data['phone'] === '628999888777'
                && str_contains($data['message'], 'Rina')
                && str_contains($data['message'], 'Ready kak?');
        });

        // Second message while the first is still unread → no extra ping.
        $this->postJson('/api/chat-toko/kirim', ['session_id' => 'sess-shop-7', 'message' => 'Halo?'])->assertOk();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_no_admin_ping_when_number_not_configured(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        \Illuminate\Support\Facades\Http::fake();

        AssistantLead::create(['session_id' => 'sess-shop-8', 'name' => 'Dodi', 'phone' => '628444']);
        $this->postJson('/api/chat-toko/kirim', ['session_id' => 'sess-shop-8', 'message' => 'Tes'])->assertOk();

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    /** Admin replies → the customer is pinged on WA once per calendar day. */
    public function test_admin_reply_notifies_customer_once_per_day(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        \Illuminate\Support\Facades\Http::fake(['pati.wablas.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(\App\Models\Role::where('slug', 'super-admin')->first());

        AssistantLead::create(['session_id' => 'sess-notif-1', 'name' => 'Ussy', 'phone' => '6283173342644']);
        SiteChatMessage::create(['session_id' => 'sess-notif-1', 'direction' => 'in', 'message' => 'Genset gitu ini th?', 'created_at' => now()]);

        // First reply of the day → one WhatsApp notification.
        $this->actingAs($admin)->postJson('/admin/chat-toko/kirim', ['sesi' => 'sess-notif-1', 'message' => 'iya kak fungsinya seperti genset.'])
            ->assertOk()->assertJsonPath('notified', true);

        \Illuminate\Support\Facades\Http::assertSent(function ($req) {
            $payload = $req['data'][0] ?? [];

            return $payload['phone'] === '6283173342644'
                && str_contains($payload['message'], 'Ussy')
                && str_contains($payload['message'], 'seperti genset');
        });

        // More replies the same day → no further notifications.
        $this->actingAs($admin)->postJson('/admin/chat-toko/kirim', ['sesi' => 'sess-notif-1', 'message' => 'ada pertanyaan lain kak?'])
            ->assertOk()->assertJsonPath('notified', false);
        $this->actingAs($admin)->postJson('/admin/chat-toko/kirim', ['sesi' => 'sess-notif-1', 'message' => 'stok ready ya'])->assertOk();

        \Illuminate\Support\Facades\Http::assertSentCount(1);
        $this->assertSame(1, SiteChatMessage::whereNotNull('notified_at')->count());
    }

    public function test_customer_is_notified_again_the_next_day(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        \Illuminate\Support\Facades\Http::fake(['pati.wablas.com/*' => \Illuminate\Support\Facades\Http::response(['status' => true], 200)]);

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(\App\Models\Role::where('slug', 'super-admin')->first());

        AssistantLead::create(['session_id' => 'sess-notif-2', 'name' => 'Ussy', 'phone' => '6283173342644']);
        // Yesterday's reply already carried a notification.
        SiteChatMessage::create([
            'session_id' => 'sess-notif-2', 'direction' => 'out', 'message' => 'balasan kemarin',
            'notified_at' => now()->subDay(), 'created_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->postJson('/admin/chat-toko/kirim', ['sesi' => 'sess-notif-2', 'message' => 'balasan hari ini'])
            ->assertOk()->assertJsonPath('notified', true);

        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_no_customer_notification_without_a_phone_on_file(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        \Illuminate\Support\Facades\Http::fake();

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(\App\Models\Role::where('slug', 'super-admin')->first());

        $this->actingAs($admin)->postJson('/admin/chat-toko/kirim', ['sesi' => 'sess-notif-3', 'message' => 'halo'])->assertOk();

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_admin_inbox_requires_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(); // no role

        $this->actingAs($user)->get('/admin/chat-toko')->assertForbidden();
    }
}
