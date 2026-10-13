<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\WaCampaign;
use App\Models\WaCampaignTemplate;
use App\Services\WaCampaign\WaCampaignService;
use App\Services\WaCampaign\WaCampaignSettings;
use App\Services\WaCampaign\WaContactService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WaCampaignStarterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Starter: pengaturan konservatif + template + campaign draf Seraphim untuk kontak legacy, siap "Mulai". */
class WaCampaignStarterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_creates_settings_template_and_ready_draft_campaign(): void
    {
        $product = Product::factory()->create(['sku' => 'SERAPHIM-SRP-345-6MA-DG', 'name' => 'Panel Surya Seraphim 345Wp', 'slug' => 'seraphim-345', 'status' => 'published', 'price' => 850_000]);
        $contacts = app(WaContactService::class);
        $legacy = $contacts->upsert('081200001111', ['name' => 'Pak Hendra', 'source' => 'ridl', 'tags' => ['legacy', 'sales-EPR']]);
        $contacts->optIn($legacy, 'ridl', 'customer lama');
        $web = $contacts->upsert('081200002222', ['name' => 'Web', 'source' => 'checkout']);
        $contacts->optIn($web, 'checkout', 'centang');

        $this->seed(WaCampaignStarterSeeder::class);
        $this->seed(WaCampaignStarterSeeder::class); // idempotent

        $s = app(WaCampaignSettings::class);
        $this->assertSame(100, $s->get('per_day'));
        $this->assertSame(2, $s->get('per_minute'));
        $this->assertSame('09:00', $s->get('window_start'));
        $this->assertSame('1,2,3,4,5', $s->get('send_days'));
        $this->assertSame(1, WaCampaignTemplate::where('name', WaCampaignStarterSeeder::TEMPLATE_NAME)->count());

        $campaign = WaCampaign::where('name', WaCampaignStarterSeeder::CAMPAIGN_NAME)->firstOrFail();
        $this->assertSame(1, WaCampaign::count());
        $this->assertSame(WaCampaign::DRAFT, $campaign->status);
        $this->assertSame($product->id, $campaign->product_id);
        $this->assertSame(['tags' => ['legacy'], 'sources' => ['ridl']], $campaign->segment);
        // Hanya kontak legacy ber-izin yang jadi penerima; pesan personal + link UTM + footer STOP.
        $this->assertSame(1, app(WaCampaignService::class)->audienceCount($campaign->segment));
        $rendered = app(WaCampaignService::class)->render($campaign, $legacy);
        $this->assertStringContainsString('Halo Kak Pak', $rendered);
        $this->assertStringContainsString('utm_campaign=seraphim-345-legacy', $rendered);
        $this->assertStringContainsString('Balas STOP', $rendered);

        $this->seed(RoleSeeder::class);
        $sales = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $sales->roles()->attach(Role::where('slug', 'admin-sales')->first());
        $this->actingAs($sales)->get(route('admin.wa-campaign.show', $campaign))->assertOk()->assertSee('Mulai / Jadwalkan')->assertSee('Seraphim');
        $this->actingAs($sales)->get(route('admin.wa-campaign.edit', $campaign))->assertOk()->assertSee('Customer lama ridlabs');
    }
}
