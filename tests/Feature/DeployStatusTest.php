<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Kartu "Status deploy otomatis" di Pengaturan membaca state & log deploy/auto-deploy.sh. */
class DeployStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/deploy-test-backup'));
        parent::tearDown();
    }

    public function test_settings_page_shows_live_commit_and_failures_from_deploy_log(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $admin->roles()->attach(Role::where('slug', 'super-admin')->first());

        $dir = storage_path('app/deploy');
        $log = storage_path('logs/deploy.log');
        $backup = storage_path('app/deploy-test-backup');
        File::ensureDirectoryExists($backup);
        if (File::isDirectory($dir)) {
            File::copyDirectory($dir, $backup.'/state');
            File::deleteDirectory($dir);
        }
        if (File::exists($log)) {
            File::move($log, $backup.'/deploy.log');
        }

        try {
            $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Belum aktif');

            File::ensureDirectoryExists($dir);
            File::put($dir.'/last-ok', "5d456be0123456789abcdef\n");
            File::put($log, "2026-10-19 11:28:57 === deploy 5d456be → 7b30a1f (main)\nNothing to migrate.\n2026-10-19 11:28:58 OK — live di 7b30a1f: tes deploy\n");
            $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()
                ->assertSee('✅ Live')->assertSee('5d456be')->assertSee('OK — live di 7b30a1f')->assertDontSee('Nothing to migrate');

            File::put($dir.'/failed', '7b30a1f');
            File::append($log, "2026-10-19 11:30:00 GAGAL: php artisan migrate — perbaiki lalu push ulang\n");
            $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Deploy terakhir GAGAL')->assertSee('GAGAL: php artisan migrate');
        } finally {
            File::deleteDirectory($dir);
            File::delete($log);
            if (File::isDirectory($backup.'/state')) {
                File::copyDirectory($backup.'/state', $dir);
            }
            if (File::exists($backup.'/deploy.log')) {
                File::move($backup.'/deploy.log', $log);
            }
        }
    }
}
