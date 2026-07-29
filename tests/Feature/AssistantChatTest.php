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

    public function test_produk_token_picks_the_cards_and_is_stripped(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => "Buat camping, BLUETTI EB3A paling pas Kak 😊\n[[PRODUK BLUETTI EB3A Portable Power Station]]"]],
        ], 200)]);

        Product::factory()->create(['name' => 'BLUETTI EB3A Portable Power Station (268Wh / 600W)', 'status' => 'published', 'price' => 3699000, 'stock' => 5]);
        // A paket that would otherwise ride along as a retrieval candidate.
        Product::factory()->create(['name' => 'Paket PLTS Rumah Hemat', 'slug' => 'paket-plts-rumah-hemat', 'status' => 'published', 'price' => 25000000, 'stock' => 2]);

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'paket buat rumah atau power station buat camping ya enaknya'])->assertOk();

        $this->assertStringNotContainsString('[[PRODUK', $res->json('reply'));
        $this->assertCount(1, $res->json('products'));
        $res->assertJsonPath('products.0.name', 'BLUETTI EB3A Portable Power Station (268Wh / 600W)');
    }

    public function test_portable_question_does_not_summon_paket_cards(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Ada beberapa pilihan power station Kak 😊']],
        ], 200)]);

        Product::factory()->create(['name' => 'BLUETTI AC70P Portable Power Station', 'status' => 'published', 'price' => 8299000, 'stock' => 3]);
        Product::factory()->create(['name' => 'Paket PLTS Rumah Hemat', 'slug' => 'paket-plts-rumah-hemat-2', 'status' => 'published', 'price' => 25000000, 'stock' => 2]);

        // No [[PRODUK]] token in the reply → fallback to retrieval candidates,
        // which must NOT be padded with pakets for a camping question.
        $res = $this->postJson('/api/asisten/tanya', ['message' => 'power station buat camping yang bagus apa?'])->assertOk();

        $names = collect($res->json('products'))->pluck('name');
        $this->assertTrue($names->contains('BLUETTI AC70P Portable Power Station'));
        $this->assertFalse($names->contains('Paket PLTS Rumah Hemat'));
    }

    public function test_product_slug_pins_the_product_into_context(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Ready Kak, stoknya masih ada 😊']],
        ], 200)]);

        $p = Product::factory()->create(['name' => 'Inverter Hybrid XYZ 5000W', 'slug' => 'inverter-hybrid-xyz-5000w', 'status' => 'published', 'price' => 15000000, 'stock' => 3]);

        // "barang ini ready?" carries no product keyword at all — the slug from
        // the product page must keep the answer grounded on that product.
        $res = $this->postJson('/api/asisten/tanya', ['message' => 'barang ini ready?', 'product_slug' => $p->slug])->assertOk();

        $res->assertJsonPath('products.0.name', 'Inverter Hybrid XYZ 5000W');
        Http::assertSent(fn ($request) => str_contains($request['system'], 'KONTEKS HALAMAN')
            && str_contains($request['system'], 'Inverter Hybrid XYZ 5000W'));
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
