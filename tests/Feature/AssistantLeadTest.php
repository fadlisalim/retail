<?php

namespace Tests\Feature;

use App\Models\AssistantLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantLeadTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'services.anthropic.enabled' => true,
            'services.anthropic.api_key' => 'sk-test',
            'services.anthropic.model' => 'claude-sonnet-5',
        ]);
    }

    private function fakeReply(string $text): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => $text]],
        ], 200)]);
    }

    public function test_data_token_is_stripped_and_lead_saved_with_normalized_phone(): void
    {
        $this->enable();
        $this->fakeReply("Siap Kak Budi, nanti tim kami hubungi ya 😊\n[[DATA nama=\"Budi Santoso\" hp=\"0812-3456-789\"]]");

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'nama saya Budi Santoso, hp 0812-3456-789', 'session_id' => 'sess-lead-1'])
            ->assertOk();

        $this->assertStringNotContainsString('[[DATA', $res->json('reply'));
        $this->assertStringNotContainsString('0812', $res->json('reply'));

        $lead = AssistantLead::where('session_id', 'sess-lead-1')->first();
        $this->assertNotNull($lead);
        $this->assertSame('Budi Santoso', $lead->name);
        $this->assertSame('628123456789', $lead->phone); // 08… → 628…, digits only
    }

    public function test_lead_is_upserted_per_session_and_partial_data_merges(): void
    {
        $this->enable();

        $wrap = fn (string $text) => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => $text]],
        ], 200);
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->pushResponse($wrap("Halo Kak Sari! 😊\n[[DATA nama=\"Sari\"]]"))
            ->pushResponse($wrap("Tercatat ya Kak Sari 🙌\n[[DATA hp=\"081298765432\"]]"))]);

        // Turn 1: name only.
        $this->postJson('/api/asisten/tanya', ['message' => 'aku Sari', 'session_id' => 'sess-lead-2'])->assertOk();

        // Turn 2: phone only — must merge into the same row, keeping the name.
        $this->postJson('/api/asisten/tanya', ['message' => 'hp ku 081298765432', 'session_id' => 'sess-lead-2'])->assertOk();

        $this->assertSame(1, AssistantLead::count());
        $lead = AssistantLead::first();
        $this->assertSame('Sari', $lead->name);
        $this->assertSame('6281298765432', $lead->phone);
    }

    public function test_reply_without_token_saves_nothing(): void
    {
        $this->enable();
        $this->fakeReply('Harga panelnya Rp 1.000.000 ya Kak 😊');

        $this->postJson('/api/asisten/tanya', ['message' => 'harga panel?', 'session_id' => 'sess-lead-3'])->assertOk();

        $this->assertSame(0, AssistantLead::count());
    }

    public function test_implausible_phone_is_dropped_but_name_kept(): void
    {
        $this->enable();
        $this->fakeReply("Oke Kak Dodi!\n[[DATA nama=\"Dodi\" hp=\"123\"]]");

        $this->postJson('/api/asisten/tanya', ['message' => 'aku Dodi, hp 123', 'session_id' => 'sess-lead-4'])->assertOk();

        $lead = AssistantLead::first();
        $this->assertSame('Dodi', $lead->name);
        $this->assertNull($lead->phone);
    }

    public function test_contact_form_saves_lead_and_returns_whatsapp_link(): void
    {
        $res = $this->postJson('/api/asisten/kontak', [
            'session_id' => 'sess-form-1',
            'name' => 'Rina',
            'phone' => '0813-1111-2222',
            'need' => 'Paket PLTS untuk rumah 2200 VA',
        ])->assertOk()->assertJsonPath('ok', true);

        $lead = AssistantLead::where('session_id', 'sess-form-1')->first();
        $this->assertSame('Rina', $lead->name);
        $this->assertSame('6281311112222', $lead->phone);
        $this->assertSame('Paket PLTS untuk rumah 2200 VA', $lead->need);
        // Pre-filled CS message carries the name + need.
        $this->assertStringContainsString('Rina', urldecode((string) $res->json('whatsapp')));
    }

    public function test_contact_form_requires_all_fields_and_a_valid_phone(): void
    {
        $this->postJson('/api/asisten/kontak', ['session_id' => 'sess-form-2', 'name' => 'X', 'phone' => '08131112222'])
            ->assertStatus(422); // need missing

        $this->postJson('/api/asisten/kontak', [
            'session_id' => 'sess-form-2', 'name' => 'X', 'phone' => '123', 'need' => 'tanya stok',
        ])->assertStatus(422); // implausible phone

        $this->assertSame(0, AssistantLead::count());
    }

    public function test_prompt_uses_kakak_persona_and_asks_for_contact(): void
    {
        $this->enable();
        $this->fakeReply('Halo Kak! 😊');

        $this->postJson('/api/asisten/tanya', ['message' => 'halo'])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['system'], 'Kakak')
            && str_contains($request['system'], '[[DATA')
            && str_contains($request['system'], 'nomor HP'));
    }
}
