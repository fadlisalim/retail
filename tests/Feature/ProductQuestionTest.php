<?php

namespace Tests\Feature;

use App\Models\ProductQuestion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tanya Jawab produk: wajib login + WA, jawaban admin terkirim ke WA penanya,
 * dan nomornya tampil tersensor di halaman publik.
 */
class ProductQuestionTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'customer-service')->first());

        return $user;
    }

    public function test_guests_cannot_ask_and_are_pointed_to_login(): void
    {
        $product = $this->stockedProduct(5, ['status' => 'published', 'published_at' => now()]);

        $this->post(route('questions.store', $product->slug), ['question' => 'Garansinya berapa lama?'])
            ->assertRedirect(route('login'));

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Masuk dulu untuk bertanya');
    }

    public function test_asking_requires_a_whatsapp_number_when_the_profile_has_none(): void
    {
        $product = $this->stockedProduct(5, ['status' => 'published', 'published_at' => now()]);
        $user = $this->customer(['phone' => null, 'whatsapp' => null]);

        $this->actingAs($user)
            ->post(route('questions.store', $product->slug), ['question' => 'Garansinya berapa lama?'])
            ->assertSessionHasErrors('whatsapp');

        $this->actingAs($user)
            ->post(route('questions.store', $product->slug), [
                'question' => 'Garansinya berapa lama?',
                'whatsapp' => '0812-3456-7890',
            ])->assertSessionHasNoErrors();

        $question = ProductQuestion::firstOrFail();
        $this->assertSame('6281234567890', $question->phone);
        // Nomornya tersimpan ke profil agar tidak ditanya lagi.
        $this->assertSame('6281234567890', $user->fresh()->whatsapp);
    }

    public function test_a_saved_profile_number_is_used_without_asking_again(): void
    {
        $product = $this->stockedProduct(5, ['status' => 'published', 'published_at' => now()]);
        $user = $this->customer(['whatsapp' => '628999888777']);

        $this->actingAs($user)
            ->post(route('questions.store', $product->slug), ['question' => 'Ready stok tidak?'])
            ->assertSessionHasNoErrors();

        $this->assertSame('628999888777', ProductQuestion::firstOrFail()->phone);
    }

    public function test_the_public_page_shows_a_censored_number_never_the_full_one(): void
    {
        $product = $this->stockedProduct(5, ['status' => 'published', 'published_at' => now()]);
        $product->questions()->create([
            'name' => 'Budi', 'phone' => '6281234567890',
            'question' => 'Bisa kirim ke Papua?', 'is_visible' => true,
        ]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('0812••••••90')
            ->assertDontSee('6281234567890')
            ->assertDontSee('081234567890');
    }

    public function test_admin_answer_appears_publicly_and_is_sent_to_the_asker_wa(): void
    {
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.base_url' => 'https://pati.wablas.com']);
        Http::fake(['pati.wablas.com/*' => Http::response(['status' => true], 200)]);

        $product = $this->stockedProduct(5, ['status' => 'published', 'published_at' => now()]);
        $question = $product->questions()->create([
            'name' => 'Budi', 'phone' => '6281234567890',
            'question' => 'Bisa kirim ke Papua?', 'is_visible' => true,
        ]);

        $this->actingAs($this->moderator())
            ->post(route('admin.questions.answer', $question), ['answer' => 'Bisa Kak, via kargo laut.'])
            ->assertRedirect();

        $this->assertNotNull($question->answers()->first()->wa_notified_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'send-message')
            && str_contains(json_encode($request->data()), 'Bisa Kak, via kargo laut.'));

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Bisa Kak, via kargo laut.')
            ->assertSee('Rekasurya');
    }

    public function test_hidden_questions_disappear_from_the_product_page(): void
    {
        $product = $this->stockedProduct(5, ['status' => 'published', 'published_at' => now()]);
        $question = $product->questions()->create([
            'name' => 'Budi', 'phone' => '628123', 'question' => 'Pertanyaan tidak pantas', 'is_visible' => true,
        ]);

        $this->actingAs($this->moderator())
            ->post(route('admin.questions.visibility', $question))
            ->assertRedirect();

        $this->assertFalse($question->fresh()->is_visible);
        $this->get(route('products.show', $product->slug))->assertDontSee('Pertanyaan tidak pantas');
    }

    public function test_the_admin_question_page_is_gated_by_the_review_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $keuangan = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $keuangan->roles()->attach(Role::where('slug', 'admin-keuangan')->first());

        $this->actingAs($keuangan)->get(route('admin.questions.index'))->assertForbidden();
        $this->actingAs($this->moderator())->get(route('admin.questions.index'))->assertOk();
    }
}
