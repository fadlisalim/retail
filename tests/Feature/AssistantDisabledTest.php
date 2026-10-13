<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Saklar "Asisten AI Kirana" di Pengaturan: mati → semua titik konsultasi langsung ke WhatsApp CS. */
class AssistantDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_the_assistant_routes_every_consultation_entry_point_to_whatsapp(): void
    {
        config(['services.anthropic.enabled' => true, 'services.anthropic.api_key' => 'sk-test']);
        $s = app(SettingService::class);
        $s->set('whatsapp.number', '628112241107', 'string', 'whatsapp');
        $s->set('whatsapp.greeting', 'Halo Rekasurya, saya ingin berkonsultasi.', 'string', 'whatsapp');

        // Aktif (default): widget Kirana + halaman /konsultasi.
        $this->get('/')->assertOk()->assertSee('Kirana')->assertSee('CS 24jam');
        $this->get('/konsultasi')->assertOk()->assertSee('Konsultan Energi');

        $s->set('chat.assistant_enabled', false, 'boolean', 'chat');
        $wa = 'https://wa.me/628112241107?text='.rawurlencode('Halo Rekasurya, saya ingin berkonsultasi.');

        $home = $this->get('/')->assertOk();
        $home->assertDontSee('Kirana')->assertDontSee('CS 24jam')->assertSee('Konsultasi via WhatsApp')->assertSee('Konsultasi Gratis via WhatsApp')->assertSee($wa, false);
        $this->get('/konsultasi')->assertRedirect($wa);

        // Endpoint chat (halaman lama yang masih terbuka) tidak memanggil AI, cukup serahkan ke WhatsApp.
        Http::fake();
        $this->postJson(route('assistant.chat'), ['message' => 'Halo'])->assertOk()
            ->assertJsonPath('escalate', true)
            ->assertJsonFragment(['reply' => 'Halo! 👋 Konsultasi sekarang langsung dengan tim Energi.Click via WhatsApp — klik tombol di bawah ya.']);
        Http::assertNothingSent();

        // Admin bisa menyalakan lagi lewat Pengaturan.
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Asisten AI');
        $this->actingAs($admin)->put(route('admin.settings.update'), ['tax_ppn_percent' => 11, 'chat_assistant_enabled' => 1, 'whatsapp_number' => '628112241107'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($s->assistantEnabled());
        $this->get('/')->assertOk()->assertSee('Kirana');
        $this->actingAs($admin)->put(route('admin.settings.update'), ['tax_ppn_percent' => 11, 'chat_assistant_enabled' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($s->assistantEnabled());
    }
}
