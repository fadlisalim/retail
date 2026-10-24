<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Usulan agent: laporan + link PR dikirim ke WA pemilik, hanya bila laporan sudah memuat link PR. */
class NotifyDeployProposalTest extends TestCase
{
    private const SHA = 'abc1234def5678abc1234def5678abc1234def56';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tes', 'rekasurya.deploy.report_whatsapp' => '08112231107']);
        Http::fake(['*' => Http::response(['status' => true])]);
    }

    private function report(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'proposal');
        file_put_contents($file, $content);

        return $file;
    }

    public function test_sends_report_and_pr_link_to_owner_whatsapp(): void
    {
        $file = $this->report("- Perbaiki kontras tombol di HP\n- Tambah alt foto produk\n\nPR: https://github.com/fadlisalim/retail/pull/12\n");

        $this->artisan('deploy:notify-proposal', ['sha' => self::SHA, '--file' => $file])->assertSuccessful();

        Http::assertSent(function ($request) {
            $data = $request['data'][0];

            return $data['phone'] === '628112231107'
                && str_contains($data['message'], 'Perbaiki kontras tombol di HP')
                && str_contains($data['message'], 'https://github.com/fadlisalim/retail/pull/12')
                && substr_count($data['message'], 'pull/12') === 1;
        });
    }

    public function test_waits_until_report_has_pr_link(): void
    {
        $this->artisan('deploy:notify-proposal', ['sha' => self::SHA, '--file' => $this->report("- Perbaiki kontras tombol\n")])->assertFailed();
        $this->artisan('deploy:notify-proposal', ['sha' => self::SHA])->assertFailed();

        Http::assertNothingSent();
    }
}
