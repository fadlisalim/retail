<?php

namespace Tests\Feature;

use App\Models\AssistantConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kirim foto di chat Kirana: unggah → path dikirim bersama pesan → model
 * menerima blok gambar (vision) → foto tercatat & muncul lagi di riwayat.
 */
class AssistantPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'services.anthropic.enabled' => true,
            'services.anthropic.api_key' => 'sk-test',
            'services.anthropic.base_url' => 'https://api.anthropic.com',
        ]);
    }

    public function test_a_photo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $res = $this->postJson('/api/asisten/unggah', [
            'image' => UploadedFile::fake()->image('nameplate.jpg', 800, 600),
        ])->assertOk()->assertJsonPath('ok', true);

        Storage::disk('public')->assertExists($res->json('path'));
        $this->assertStringStartsWith('chat-uploads/', $res->json('path'));
    }

    public function test_non_images_are_rejected(): void
    {
        Storage::fake('public');

        $this->postJson('/api/asisten/unggah', [
            'image' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_the_model_receives_the_photo_as_an_image_block(): void
    {
        Storage::fake('public');
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Dari nameplate-nya, ini inverter 3000W 24V ya Kak.']],
        ], 200)]);

        $path = 'chat-uploads/2026/09/uji-nameplate.jpg';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('n.jpg', 400, 300)->getContent());

        $this->postJson('/api/asisten/tanya', [
            'message' => 'ini inverter saya, baterai yang cocok apa?',
            'image' => $path,
            'session_id' => 'sess-foto-1',
        ])->assertOk();

        Http::assertSent(function ($request) {
            $messages = $request['messages'];
            $last = end($messages);
            if (! is_array($last['content'])) {
                return false;
            }
            $types = array_column($last['content'], 'type');

            return in_array('image', $types, true)
                && in_array('text', $types, true)
                && $last['content'][array_search('image', $types, true)]['source']['media_type'] === 'image/jpeg'
                && str_contains($request['system'], 'FOTO DARI PELANGGAN');
        });

        // Foto tercatat di transkrip untuk riwayat & log admin.
        $this->assertSame($path, AssistantConversation::first()->image_path);
    }

    public function test_a_photo_without_text_is_accepted(): void
    {
        Storage::fake('public');
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Terlihat meteran 2200 VA ya Kak.']],
        ], 200)]);

        $path = 'chat-uploads/2026/09/meteran.png';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('m.png', 300, 300)->getContent());

        $this->postJson('/api/asisten/tanya', ['image' => $path, 'session_id' => 'sess-foto-2'])
            ->assertOk()
            ->assertJsonPath('reply', 'Terlihat meteran 2200 VA ya Kak.');
    }

    public function test_history_returns_the_photo_url(): void
    {
        AssistantConversation::create([
            'session_id' => 'sess-foto-3',
            'message' => 'ini atap saya',
            'image_path' => 'chat-uploads/2026/09/atap.jpg',
            'reply' => 'Atapnya cocok untuk 8 panel Kak.',
            'answered' => true,
            'created_at' => now(),
        ]);

        $this->getJson('/api/asisten/riwayat?session_id=sess-foto-3')
            ->assertOk()
            ->assertJsonPath('messages.0.image', asset('storage/chat-uploads/2026/09/atap.jpg'));
    }

    /** Path di luar folder chat-uploads ditolak (anti path liar). */
    public function test_foreign_paths_are_ignored(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Siap Kak.']],
        ], 200)]);

        $this->postJson('/api/asisten/tanya', [
            'message' => 'halo',
            'image' => '../../.env',
        ])->assertStatus(422);
    }
}
