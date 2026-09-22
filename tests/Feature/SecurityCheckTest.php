<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * php artisan security:check — pemeriksaan pasca-insiden token Wablas:
 * menangkap konfigurasi & file server yang membocorkan rahasia.
 */
class SecurityCheckTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function test_flags_debug_missing_webhook_token_and_env_copies(): void
    {
        config(['app.env' => 'production', 'app.debug' => true, 'services.wablas.enabled' => true, 'services.wablas.webhook_token' => '']);
        $copy = base_path('.env.save');
        file_put_contents($copy, "WABLAS_TOKEN=abc\n");
        $this->cleanup[] = $copy;
        $pub = public_path('phpinfo.php');
        file_put_contents($pub, '<?php phpinfo();');
        $this->cleanup[] = $pub;

        $this->assertSame(1, Artisan::call('security:check'));
        $out = Artisan::output();

        $this->assertStringContainsString('GAWAT', $out);
        $this->assertStringContainsString('APP_DEBUG', $out);
        $this->assertStringContainsString('halaman error bisa membocorkan', $out);
        $this->assertStringContainsString('KOSONG — siapa pun bisa mengirim pesan masuk palsu', $out);
        $this->assertStringContainsString('.env.save', $out);
        $this->assertStringContainsString('phpinfo.php bisa diunduh publik', $out);
        $this->assertStringContainsString('regenerate', $out);
    }

    public function test_passes_on_a_hardened_configuration(): void
    {
        config([
            'app.env' => 'production', 'app.debug' => false, 'session.secure' => true,
            'services.wablas.enabled' => true, 'services.wablas.token' => 'tok', 'services.wablas.webhook_token' => str_repeat('x', 32),
        ]);

        Artisan::call('security:check');
        $out = Artisan::output();

        // Izin file .env di mesin test bisa apa saja — yang dipastikan: konfigurasi
        // aplikasi & file tidak menghasilkan temuan.
        foreach (['halaman error bisa membocorkan', 'KOSONG — siapa pun', 'Salinan .env      | .env.', 'bisa diunduh publik'] as $needle) {
            $this->assertStringNotContainsString($needle, $out);
        }
        $this->assertStringContainsString('| OK    | APP_DEBUG', $out);
        $this->assertStringContainsString('| OK    | WABLAS_WEBHOOK_TOKEN', $out);
        $this->assertStringContainsString('curl -sI', $out);
    }
}
