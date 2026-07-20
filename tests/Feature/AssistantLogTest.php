<?php

namespace Tests\Feature;

use App\Models\AssistantConversation;
use App\Models\AssistantDailyStat;
use App\Models\AssistantDailyTerm;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chat_exchange_is_logged_and_rolled_up(): void
    {
        config(['services.anthropic.enabled' => true, 'services.anthropic.api_key' => 'sk-test', 'services.anthropic.model' => 'claude-sonnet-5']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'Panel surya AIKO 650Wp Rp 2.490.000.']],
        ], 200)]);
        Product::factory()->create(['name' => 'Panel Surya AIKO 650Wp', 'slug' => 'panel-surya-aiko-650wp', 'status' => 'published']);

        $this->postJson('/api/asisten/tanya', ['message' => 'panel surya aiko', 'session_id' => 'sess-abc'])->assertOk();

        $this->assertDatabaseCount('assistant_conversations', 1);
        $conv = AssistantConversation::first();
        $this->assertTrue($conv->answered);
        $this->assertSame('sess-abc', $conv->session_id);
        $this->assertContains('panel-surya-aiko-650wp', $conv->product_slugs);

        $stat = AssistantDailyStat::whereDate('day', now()->toDateString())->first();
        $this->assertSame(1, (int) $stat->messages);
        $this->assertSame(1, (int) $stat->answered);
        $this->assertSame(1, (int) $stat->sessions);

        $this->assertTrue(AssistantDailyTerm::where('type', 'keyword')->where('term', 'panel')->exists());
        $this->assertTrue(AssistantDailyTerm::where('type', 'product')->where('term', 'panel-surya-aiko-650wp')->exists());
    }

    public function test_retrieval_excludes_products_matched_only_by_description(): void
    {
        // A battery whose description merely mentions "panel surya" must NOT
        // surface for a "panel surya" question (regression: relevance leak).
        Product::factory()->create(['name' => 'Panel Surya Mono 550Wp', 'status' => 'published']);
        Product::factory()->create([
            'name' => 'Baterai Lithium 5kWh',
            'status' => 'published',
            'short_description' => 'Baterai cocok untuk sistem panel surya rumah.',
        ]);

        config(['services.anthropic.enabled' => false]); // retrieval still runs
        Http::fake();

        $res = $this->postJson('/api/asisten/tanya', ['message' => 'panel surya'])->assertOk();
        $names = collect($res->json('products'))->pluck('name');

        $this->assertTrue($names->contains('Panel Surya Mono 550Wp'));
        $this->assertFalse($names->contains('Baterai Lithium 5kWh'));
    }

    public function test_prune_deletes_old_transcripts_but_keeps_stats_longer(): void
    {
        config(['services.anthropic.log_retention_days' => 30, 'services.anthropic.stats_retention_days' => 180]);

        AssistantConversation::create(['message' => 'lama', 'reply' => 'x', 'answered' => true, 'created_at' => now()->subDays(40)]);
        AssistantConversation::create(['message' => 'baru', 'reply' => 'x', 'answered' => true, 'created_at' => now()->subDays(5)]);
        AssistantDailyStat::create(['day' => now()->subDays(200)->toDateString(), 'messages' => 3]);
        AssistantDailyStat::create(['day' => now()->subDays(100)->toDateString(), 'messages' => 3]);

        $this->artisan('assistant:prune')->assertSuccessful();

        $this->assertSame(1, AssistantConversation::count()); // 40-day one gone, 5-day kept
        $this->assertNull(AssistantConversation::where('message', 'lama')->first());
        $this->assertSame(1, AssistantDailyStat::count()); // 200-day gone, 100-day kept
    }

    public function test_admin_can_view_the_cs_dashboard(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());
        AssistantConversation::create(['session_id' => 'sess-xyz', 'message' => 'apakah bluetti ada?', 'reply' => 'Ada beberapa model BLUETTI.', 'answered' => true, 'created_at' => now()]);

        // Grouped-by-session view (default) shows the session and its thread.
        $this->actingAs($admin)->get('/admin/cs-assistant')
            ->assertOk()
            ->assertSee('CS Assistant')
            ->assertSee('Per Sesi')
            ->assertSee('apakah bluetti ada?');

        // Flat view also renders.
        $this->actingAs($admin)->get('/admin/cs-assistant?view=flat')
            ->assertOk()
            ->assertSee('apakah bluetti ada?');
    }

    public function test_non_permitted_staff_cannot_view_dashboard(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $staff->roles()->attach(Role::where('slug', 'admin-gudang')->first()); // no assistant.view

        $this->actingAs($staff)->get('/admin/cs-assistant')->assertForbidden();
    }
}
