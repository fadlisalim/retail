<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'services.anthropic.enabled' => true,
            'services.anthropic.api_key' => 'sk-test',
            'services.anthropic.base_url' => 'https://api.anthropic.com',
            'services.anthropic.model' => 'claude-sonnet-5',
        ]);
    }

    public function test_chat_returns_reply_and_grounds_on_catalog(): void
    {
        $this->enable();
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => 'Panel Surya 550Wp harganya Rp 1.000.000.']],
            ], 200),
        ]);

        Product::factory()->create(['name' => 'Panel Surya 550Wp Mono', 'status' => 'published', 'price' => 1000000, 'stock' => 5]);

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'Ada panel surya 550Wp?']);

        $res->assertOk()
            ->assertJsonPath('reply', 'Panel Surya 550Wp harganya Rp 1.000.000.')
            ->assertJsonPath('products.0.name', 'Panel Surya 550Wp Mono');

        // The catalogue context (real product) + Kirana persona are in the system prompt.
        Http::assertSent(fn ($request) => str_contains($request['system'], 'Panel Surya 550Wp Mono')
            && str_contains($request['system'], 'Kirana')
            && $request['model'] === 'claude-sonnet-5'
            && $request['messages'][0]['content'] === 'Ada panel surya 550Wp?');
    }

    public function test_history_is_forwarded_to_the_model(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Baik.']],
        ], 200)]);

        $this->postJson('/api/asisten/tanya', [
            'message' => 'Yang mana lebih murah?',
            'history' => [
                ['role' => 'user', 'content' => 'Halo'],
                ['role' => 'assistant', 'content' => 'Halo, ada yang bisa dibantu?'],
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            $msgs = $request['messages'];

            return count($msgs) === 3 && $msgs[0]['content'] === 'Halo' && end($msgs)['content'] === 'Yang mana lebih murah?';
        });
    }

    public function test_disabled_service_returns_friendly_fallback_without_calling_api(): void
    {
        config(['services.anthropic.enabled' => false]);
        Http::fake();

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'Halo']);

        $res->assertOk();
        $this->assertNotEmpty($res->json('reply'));
        $res->assertJsonPath('escalate', true); // fallback offers the WA hand-off
        Http::assertNothingSent();
    }

    public function test_wa_marker_escalates_but_gates_the_number_behind_the_contact_form(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => "Butuh konsultasi lebih detail ya.\n[[WA]]"]],
        ], 200)]);

        // No lead on file for this session → the widget must show the contact
        // form instead of the WhatsApp link (number is not revealed yet).
        $res = $this->postJson('/api/asisten/tanya', ['message' => 'minta penawaran instalasi', 'session_id' => 'sess-gate-1'])->assertOk();

        $res->assertJsonPath('escalate', true)
            ->assertJsonPath('whatsapp', null)
            ->assertJsonPath('lead_form', true);
        $this->assertStringNotContainsString('[[WA]]', $res->json('reply'));
    }

    public function test_wa_link_is_released_once_the_session_lead_is_complete(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => "Silakan lanjut ke CS ya.\n[[WA]]"]],
        ], 200)]);

        \App\Models\AssistantLead::create(['session_id' => 'sess-gate-2', 'name' => 'Budi', 'phone' => '628123456789']);

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'mau konsultasi', 'session_id' => 'sess-gate-2'])->assertOk();

        $res->assertJsonPath('escalate', true)->assertJsonPath('lead_form', false);
        $this->assertNotNull($res->json('whatsapp'));
    }

    public function test_normal_answer_does_not_escalate(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Harganya Rp 1.000.000.']],
        ], 200)]);

        $this->postJson('/api/asisten/tanya', ['message' => 'harga berapa'])
            ->assertOk()
            ->assertJsonPath('escalate', false)
            ->assertJsonPath('whatsapp', null);
    }

    public function test_long_restored_history_is_accepted(): void
    {
        // Regression: after history-restore, the widget could hold 30 bubbles;
        // sending them all used to fail validation (max:20) and every message
        // after that errored. Now the client caps at 10 and the server takes 30.
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Siap Kak! 😊']],
        ], 200)]);

        $history = [];
        for ($i = 0; $i < 12; $i++) {
            $history[] = ['role' => 'user', 'content' => "pertanyaan {$i}"];
            $history[] = ['role' => 'assistant', 'content' => "jawaban {$i}"];
        }

        $this->postJson('/api/asisten/tanya', ['message' => 'lanjut ya', 'history' => $history])
            ->assertOk()
            ->assertJsonPath('reply', 'Siap Kak! 😊');
    }

    public function test_message_is_required(): void
    {
        $this->postJson('/api/asisten/tanya', ['message' => ''])->assertStatus(422);
    }
}
