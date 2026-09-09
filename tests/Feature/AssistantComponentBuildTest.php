<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Chatbot merakit sistem dari komponen satuan: "mau daya 5000W lengkap panel,
 * inverter, baterai" harus menyodorkan inverter berdaya cukup + panel + baterai
 * sebagai konteks/kartu, bukan produk acak hasil pencocokan kata.
 */
class AssistantComponentBuildTest extends TestCase
{
    use RefreshDatabase;

    private Category $panel;

    private Category $inverter;

    private Category $baterai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = Category::factory()->create(['name' => 'Panel Surya', 'slug' => 'panel-surya']);
        $this->inverter = Category::factory()->create(['name' => 'Inverter', 'slug' => 'inverter']);
        $this->baterai = Category::factory()->create(['name' => 'Baterai', 'slug' => 'baterai']);
    }

    private function catalogue(): void
    {
        Product::factory()->create(['name' => 'Inverter Hybrid Mini 3000W', 'category_id' => $this->inverter->id]);
        Product::factory()->create(['name' => 'Inverter Hybrid Pas 6kW', 'category_id' => $this->inverter->id]);
        Product::factory()->create(['name' => 'Inverter Hybrid Jumbo 11000W', 'category_id' => $this->inverter->id]);
        Product::factory()->create(['name' => 'Panel Surya Mono 580Wp', 'category_id' => $this->panel->id]);
        Product::factory()->create(['name' => 'Baterai Lithium 5kWh Rack', 'category_id' => $this->baterai->id, 'sold_count' => 9]);
    }

    private function ask(string $message): TestResponse
    {
        // Anthropic dimatikan → jalur fallback; kartu produk = konteks retrieval,
        // jadi bisa diverifikasi tanpa memanggil API.
        config(['services.anthropic.enabled' => false]);
        Http::fake();

        return $this->postJson('/api/asisten/tanya', ['message' => $message]);
    }

    public function test_a_5000w_build_request_surfaces_a_big_enough_inverter_plus_panel_and_battery(): void
    {
        $this->catalogue();

        $names = collect($this->ask('mau daya 5000W lengkap dengan solar panel dan inverter dan baterai')
            ->assertOk()->json('products'))->pluck('name');

        // Inverter terdekat DI ATAS 5000W yang direkomendasikan lebih dulu —
        // bukan yang 3000W (kurang daya).
        $this->assertSame('Inverter Hybrid Pas 6kW', $names->first());
        $this->assertContains('Panel Surya Mono 580Wp', $names);
        $this->assertContains('Baterai Lithium 5kWh Rack', $names);
        $this->assertNotContains('Inverter Hybrid Mini 3000W', $names->take(2));
    }

    public function test_kilowatt_phrasing_is_understood(): void
    {
        $this->catalogue();

        $names = collect($this->ask('rakitkan sistem 10 kW dong komplit')
            ->assertOk()->json('products'))->pluck('name');

        // 10 kW → satu-satunya yang cukup adalah inverter 11000W.
        $this->assertSame('Inverter Hybrid Jumbo 11000W', $names->first());
    }

    public function test_component_specs_reach_the_model_context(): void
    {
        $this->catalogue();
        config([
            'services.anthropic.enabled' => true,
            'services.anthropic.api_key' => 'sk-test',
            'services.anthropic.base_url' => 'https://api.anthropic.com',
        ]);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Berikut rakitannya Kak.']],
        ], 200)]);

        $this->postJson('/api/asisten/tanya', ['message' => 'mau daya 5000W lengkap panel inverter baterai'])->assertOk();

        // KATALOG TERKAIT (bagian sebelum indeks) memuat komponen pilihan +
        // instruksi merakit dengan rincian harga & total.
        Http::assertSent(function ($request) {
            $context = Str::before($request['system'], 'INDEKS KATALOG LENGKAP');

            return str_contains($context, 'Inverter Hybrid Pas 6kW')
                && str_contains($context, 'Panel Surya Mono 580Wp')
                && str_contains($context, 'Baterai Lithium 5kWh Rack')
                && str_contains($request['system'], 'MERAKIT SISTEM DARI KOMPONEN SATUAN')
                && str_contains($request['system'], 'Perkiraan total')
                // Aturan kelistrikan string: Voc vs tegangan maksimum, rentang
                // MPPT, arus per tracker, dan kecocokan tegangan baterai.
                && str_contains($request['system'], 'Voc panel harus < tegangan input maksimum')
                && str_contains($request['system'], 'rentang kerja MPPT')
                && str_contains($request['system'], 'arus input maksimum per MPPT')
                && str_contains($request['system'], 'tegangan sistem baterai dengan inverter');
        });
    }

    /**
     * Parameter listrik sering ada di bagian AKHIR spesifikasi — batas potong
     * konteks tidak boleh menghilangkan rentang MPPT/arus input inverter.
     */
    public function test_electrical_specs_beyond_900_chars_are_not_truncated_away(): void
    {
        $this->catalogue();
        Product::factory()->create([
            'name' => 'Inverter Hybrid Lengkap 6000W',
            'category_id' => $this->inverter->id,
            // ~1000 karakter pembuka, data listrik setelahnya (posisi > 900).
            'specifications' => '<p>'.str_repeat('Fitur unggulan inverter hybrid. ', 32)
                .'Rentang MPPT: 120-450VDC. Arus input maksimum: 22A per tracker.</p>',
        ]);
        config([
            'services.anthropic.enabled' => true,
            'services.anthropic.api_key' => 'sk-test',
            'services.anthropic.base_url' => 'https://api.anthropic.com',
        ]);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Siap Kak.']],
        ], 200)]);

        $this->postJson('/api/asisten/tanya', ['message' => 'mau daya 5000W lengkap panel inverter baterai'])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request['system'], '120-450VDC')
            && str_contains($request['system'], '22A per tracker'));
    }

    public function test_quotation_only_components_are_not_offered_for_direct_purchase_builds(): void
    {
        $this->catalogue();
        Product::factory()->create([
            'name' => 'Inverter 3 Phase Proyek 6000W',
            'category_id' => $this->inverter->id,
            'requires_quotation' => true,
            'is_purchasable' => false,
        ]);

        $names = collect($this->ask('mau daya 5000W lengkap panel inverter baterai')
            ->assertOk()->json('products'))->pluck('name');

        $this->assertNotContains('Inverter 3 Phase Proyek 6000W', $names);
        $this->assertContains('Inverter Hybrid Pas 6kW', $names);
    }

    public function test_a_plain_single_product_question_does_not_summon_components(): void
    {
        $this->catalogue();

        $names = collect($this->ask('panel surya 580wp ready?')
            ->assertOk()->json('products'))->pluck('name');

        // Pertanyaan produk tunggal: tidak ada inverter/baterai yang ikut nimbrung.
        $this->assertNotContains('Inverter Hybrid Pas 6kW', $names);
        $this->assertNotContains('Baterai Lithium 5kWh Rack', $names);
    }
}
