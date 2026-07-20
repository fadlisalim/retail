<?php

namespace Tests\Feature;

use App\Models\AssistantConversation;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_returns_session_turns_in_order_with_product_cards(): void
    {
        $product = Product::factory()->create(['name' => 'Panel Surya 550Wp', 'slug' => 'panel-surya-550wp', 'status' => 'published']);

        AssistantConversation::create([
            'session_id' => 'sess-h1', 'message' => 'halo', 'reply' => 'Halo Kak! 😊',
            'answered' => true, 'created_at' => now()->subMinutes(2),
        ]);
        AssistantConversation::create([
            'session_id' => 'sess-h1', 'message' => 'ada panel surya?', 'reply' => 'Ada Kak, ini ya.',
            'answered' => true, 'product_slugs' => ['panel-surya-550wp'], 'created_at' => now()->subMinute(),
        ]);
        // Another session must not leak in.
        AssistantConversation::create([
            'session_id' => 'sess-other', 'message' => 'rahasia', 'reply' => 'x', 'answered' => true, 'created_at' => now(),
        ]);

        $res = $this->getJson('/api/asisten/riwayat?session_id=sess-h1')->assertOk();

        $messages = $res->json('messages');
        $this->assertCount(4, $messages); // 2 exchanges = 4 bubbles
        $this->assertSame(['user', 'assistant', 'user', 'assistant'], array_column($messages, 'role'));
        $this->assertSame('halo', $messages[0]['content']);           // oldest first
        $this->assertSame('Ada Kak, ini ya.', $messages[3]['content']);
        $this->assertSame('Panel Surya 550Wp', $messages[3]['products'][0]['name']);
        $this->assertStringNotContainsString('rahasia', json_encode($messages));
    }

    public function test_unknown_session_returns_empty(): void
    {
        $this->getJson('/api/asisten/riwayat?session_id=nope')
            ->assertOk()
            ->assertJsonPath('messages', []);
    }

    public function test_history_disabled_when_logging_off(): void
    {
        config(['services.anthropic.logging' => false]);
        AssistantConversation::create(['session_id' => 'sess-h2', 'message' => 'a', 'reply' => 'b', 'answered' => true, 'created_at' => now()]);

        $this->getJson('/api/asisten/riwayat?session_id=sess-h2')
            ->assertOk()
            ->assertJsonPath('messages', []);
    }

    public function test_session_id_is_required(): void
    {
        $this->getJson('/api/asisten/riwayat')->assertStatus(422);
    }
}
