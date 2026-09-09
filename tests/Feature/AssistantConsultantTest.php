<?php

namespace Tests\Feature;

use App\Models\AssistantDailyStat;
use App\Models\AssistantDailyTerm;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kirana sebagai konsultan penjualan: timing kartu produk diputuskan model,
 * knowledge gap tercatat, funnel klik terukur, dan halaman /konsultasi tayang.
 */
class AssistantConsultantTest extends TestCase
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

    /** Jawaban edukasi tanpa token [[PRODUK]] tidak boleh dibanjiri kartu. */
    public function test_an_education_answer_carries_no_product_cards(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Inverter hybrid bisa sekaligus mengelola baterai dan PLN, off-grid berdiri sendiri tanpa PLN.']],
        ], 200)]);

        Product::factory()->create(['name' => 'Inverter Hybrid Edu 5000W', 'status' => 'published', 'stock' => 5]);

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'apa bedanya inverter hybrid dan off-grid?'])->assertOk();

        $this->assertCount(0, $res->json('products'));
    }

    /** Prompt memuat kerangka konsultatif: link timing, keberatan, prioritas jawaban. */
    public function test_the_system_prompt_carries_the_consultative_framework(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Siap Kak.']],
        ], 200)]);

        $this->postJson('/api/asisten/tanya', ['message' => 'halo'])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['system'], 'KAPAN MENAMPILKAN KARTU PRODUK')
            && str_contains($request['system'], 'MENGGALI KEBUTUHAN')
            && str_contains($request['system'], 'MENANGANI KEBERATAN')
            && str_contains($request['system'], 'JAWAB dulu pertanyaan pelanggan')
            && str_contains($request['system'], 'KEPERCAYAAN DI ATAS PENJUALAN')
            && str_contains($request['system'], 'INFO TOKO')
            && str_contains($request['system'], '[[GAP'));
    }

    /** Token [[GAP]] dibersihkan dari balasan dan tercatat sebagai knowledge gap. */
    public function test_gap_tokens_are_stripped_and_recorded(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => "Maaf Kak, genset hybrid belum ada di katalog kami saat ini.\n[[GAP kebutuhan=\"genset hybrid solar\"]]"]],
        ], 200)]);

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'ada genset hybrid?', 'session_id' => 'sess-gap-1'])->assertOk();

        $this->assertStringNotContainsString('[[GAP', $res->json('reply'));
        $this->assertDatabaseHas('assistant_daily_terms', [
            'type' => 'gap',
            'term' => 'genset hybrid solar',
        ]);
    }

    /** Endpoint klik menghitung funnel: klik kartu produk & klik WhatsApp. */
    public function test_funnel_clicks_are_counted(): void
    {
        $p = Product::factory()->create(['name' => 'BLUETTI Klik Uji', 'slug' => 'bluetti-klik-uji', 'status' => 'published']);

        $this->postJson('/api/asisten/klik', ['type' => 'product', 'slug' => $p->slug])->assertOk();
        $this->postJson('/api/asisten/klik', ['type' => 'whatsapp'])->assertOk();

        $stat = AssistantDailyStat::first();
        $this->assertSame(1, (int) $stat->product_clicks);
        $this->assertSame(1, (int) $stat->wa_clicks);

        $this->assertDatabaseHas('assistant_daily_terms', [
            'type' => 'click', 'term' => 'bluetti-klik-uji', 'label' => 'BLUETTI Klik Uji',
        ]);

        // Tipe di luar product/whatsapp ditolak.
        $this->postJson('/api/asisten/klik', ['type' => 'checkout'])->assertStatus(422);
    }

    /** Halaman konsultasi = chat full-screen ala WA: bar Kirana, chip, composer foto. */
    public function test_the_consultation_page_renders(): void
    {
        $this->get('/konsultasi')
            ->assertOk()
            ->assertSee('Konsultan Energi')
            ->assertSee('Backup listrik saat mati lampu')
            ->assertSee('PJU tenaga surya')
            ->assertSee('Ketik pesan…')
            ->assertSee('Lampirkan foto')
            ->assertSee(route('assistant.upload'), false)
            // Mode layar penuh: footer & nav bawah tidak dirender.
            ->assertDontSee('Panduan Energi Surya');
    }

    /** Gap dari beberapa percakapan terakumulasi untuk dashboard admin. */
    public function test_repeated_gaps_accumulate(): void
    {
        $this->enable();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => "Belum tersedia Kak.\n[[GAP kebutuhan=\"pompa air tenaga surya\"]]"]],
        ], 200)]);

        $this->postJson('/api/asisten/tanya', ['message' => 'ada pompa air tenaga surya?']);
        $this->postJson('/api/asisten/tanya', ['message' => 'pompa air solar ada ga?']);

        $row = AssistantDailyTerm::where('type', 'gap')->where('term', 'pompa air tenaga surya')->first();
        $this->assertSame(2, (int) $row->count);
    }
}
