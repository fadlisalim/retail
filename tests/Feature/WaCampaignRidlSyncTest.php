<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WaContact;
use App\Services\WaCampaign\WaContactService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Sinkron kontak dari DB customer lama ridlabs: hanya yang diklaim, izin dengan bukti, STOP tidak dihidupkan. */
class WaCampaignRidlSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.ridl' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'services.ridl.enabled' => true, 'services.ridl.table' => 'customers']);
        Schema::connection('ridl')->create('customers', function ($t) {
            $t->increments('id');
            $t->string('phone')->nullable();
            $t->string('nama_customer')->nullable();
            $t->string('perusahaan')->nullable();
            $t->string('id_sales')->nullable();
            $t->timestamp('claimed_at')->nullable();
        });
        DB::connection('ridl')->table('customers')->insert([
            ['phone' => '6282328525988', 'nama_customer' => null, 'perusahaan' => null, 'id_sales' => 'FRS', 'claimed_at' => '2023-06-17 00:00:00'],
            ['phone' => '6281268123767', 'nama_customer' => 'Pak Hendra', 'perusahaan' => 'CV Surya', 'id_sales' => 'EPR', 'claimed_at' => '2026-04-01 00:00:00'],
            ['phone' => '6282276137910', 'nama_customer' => 'Belum Diklaim', 'perusahaan' => null, 'id_sales' => null, 'claimed_at' => null], // tidak ikut
            ['phone' => '8613420929674', 'nama_customer' => 'Nomor Cina', 'perusahaan' => null, 'id_sales' => 'FRS', 'claimed_at' => '2024-01-01 00:00:00'], // invalid
            ['phone' => '6289900001111', 'nama_customer' => 'Sudah Stop', 'perusahaan' => null, 'id_sales' => 'MSA', 'claimed_at' => '2025-05-05 00:00:00'],
        ]);
    }

    public function test_sync_imports_claimed_customers_with_consent_proof_and_never_revives_stop(): void
    {
        $stop = app(WaContactService::class)->upsert('089900001111', ['name' => 'Sudah Stop', 'source' => 'manual']);
        app(WaContactService::class)->optOut($stop, 'Balas STOP');

        $this->artisan('wa-campaign:sync-ridl', ['--dry-run' => true])->expectsOutputToContain('dibaca 4, kontak baru 2, diperbarui 1, izin baru 2, sudah izin 0, tetap STOP 1, nomor invalid 1 (DRY RUN)')->assertSuccessful();
        $this->assertSame(1, WaContact::count()); // dry run tidak menulis

        $this->artisan('wa-campaign:sync-ridl')->expectsOutputToContain('kontak baru 2')->assertSuccessful();
        $this->assertSame(3, WaContact::count());

        $hendra = WaContact::where('phone', '6281268123767')->firstOrFail();
        $this->assertTrue($hendra->isOptedIn());
        $this->assertSame('ridl', $hendra->source);
        $this->assertSame('Pak Hendra', $hendra->name);
        $this->assertSame(['legacy', 'sales-EPR'], $hendra->tags);
        $this->assertStringContainsString('dilayani sales EPR pada 2026-04-01', $hendra->consent_proof);
        $this->assertStringContainsString('CV Surya', $hendra->notes);
        $this->assertTrue(WaContact::where('phone', '6282328525988')->first()->isOptedIn());
        $this->assertNull(WaContact::where('phone', '6282276137910')->first());
        $this->assertTrue($stop->fresh()->isOptedOut());

        // Jalan lagi: tidak dobel, izin yang ada tidak ditimpa; filter since/limit; tanpa-izin.
        $hendra->forceFill(['consent_proof' => 'izin checkout'])->save();
        $this->artisan('wa-campaign:sync-ridl')->expectsOutputToContain('kontak baru 0, diperbarui 3, izin baru 0, sudah izin 2, tetap STOP 1')->assertSuccessful();
        $this->assertSame('izin checkout', $hendra->fresh()->consent_proof);
        $this->artisan('wa-campaign:sync-ridl', ['--since' => '2025-01-01', '--dry-run' => true])->expectsOutputToContain('dibaca 2')->assertSuccessful();
        $this->artisan('wa-campaign:sync-ridl', ['--limit' => 1, '--dry-run' => true])->expectsOutputToContain('dibaca 1')->assertSuccessful();
        WaContact::where('phone', '6282328525988')->delete();
        $this->artisan('wa-campaign:sync-ridl', ['--tanpa-izin' => true])->assertSuccessful();
        $this->assertSame('unknown', WaContact::where('phone', '6282328525988')->value('consent_status'));
    }

    public function test_sync_is_skipped_when_not_configured_and_admin_button_runs_it(): void
    {
        config(['services.ridl.enabled' => false]);
        $this->artisan('wa-campaign:sync-ridl')->expectsOutputToContain('belum diisi')->assertSuccessful();
        $this->assertSame(0, WaContact::count());

        config(['services.ridl.enabled' => true]);
        $this->seed(RoleSeeder::class);
        $sales = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $sales->roles()->attach(Role::where('slug', 'admin-sales')->first());
        $this->actingAs($sales)->get(route('admin.wa-campaign.contacts'))->assertOk()->assertSee('Sinkron ridlabs');
        $this->actingAs($sales)->post(route('admin.wa-campaign.contacts.sync-ridl'))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(3, WaContact::count());
        $this->actingAs($sales)->get(route('admin.wa-campaign.contacts'))->assertOk()->assertSee('Terakhir:');
    }
}
