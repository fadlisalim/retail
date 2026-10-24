<?php

namespace Tests\Feature;

use App\Models\DeployProposal;
use App\Services\DeployProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Agent perbaikan otomatis: laporan → link ke WA pemilik → keputusan ber-OTP →
 * Eksekusi me-merge PR di GitHub, Revisi menutup PR + saran jadi komentar PR.
 */
class NotifyDeployProposalTest extends TestCase
{
    use RefreshDatabase;

    private const SHA = 'abc1234def5678abc1234def5678abc1234def56';

    private const REPORT = "# Checkout lebih cepat di HP\n\n## Ringkasan\n- Tombol bayar lebih besar\n- Gambar katalog 40% lebih ringan\n\n## Temuan audit\nWaktu muat katalog 2,1 dtk → 1,3 dtk.\n\nPR: https://github.com/fadlisalim/retail/pull/12\n";

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wablas.enabled' => true, 'services.wablas.token' => 'tes', 'rekasurya.deploy.report_whatsapp' => '08112231107', 'rekasurya.deploy.github_repo' => 'fadlisalim/retail']);
        Http::fake([
            'api.github.com/*/pulls/12/merge' => Http::response(['merged' => true]),
            'api.github.com/*' => Http::response(['ok' => true]),
            '*' => Http::response(['status' => true]),
        ]);
    }

    private function report(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'proposal');
        file_put_contents($file, $content);

        return $file;
    }

    private function notify(): DeployProposal
    {
        $this->artisan('deploy:notify-proposal', ['sha' => self::SHA, '--file' => $this->report(self::REPORT)])->assertSuccessful();

        return DeployProposal::firstOrFail();
    }

    /** Ambil kode OTP dari pesan WA terakhir yang dikirim. */
    private function otpFromWa(): string
    {
        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            if (preg_match('/OTP.*\*(\d{6})\*/', $request['data'][0]['message'] ?? '', $m)) {
                $code = $m[1];
            }

            return true;
        });

        return (string) $code;
    }

    public function test_sends_summary_and_report_link_to_owner_whatsapp(): void
    {
        $proposal = $this->notify();

        $this->assertSame('Checkout lebih cepat di HP', $proposal->title);
        $this->assertSame(12, $proposal->pr_number);
        Http::assertSent(fn ($r) => ($r['data'][0]['phone'] ?? null) === '628112231107'
            && str_contains($r['data'][0]['message'], 'Tombol bayar lebih besar')
            && str_contains($r['data'][0]['message'], route('proposals.show', $proposal->token))
            && ! str_contains($r['data'][0]['message'], 'Waktu muat katalog'));

        $this->get(route('proposals.show', $proposal->token))->assertOk()
            ->assertSee('Checkout lebih cepat di HP')->assertSee('Waktu muat katalog')->assertSee('Kirim OTP ke WA')
            ->assertDontSee('PR: https://github.com', false);
        $this->get('/usulan/'.str_repeat('x', 48))->assertNotFound();
    }

    public function test_waits_until_report_has_pr_link(): void
    {
        $this->artisan('deploy:notify-proposal', ['sha' => self::SHA, '--file' => $this->report("# Judul\n- poin\n")])->assertFailed();
        $this->assertSame(0, DeployProposal::count());
        Http::assertNothingSent();
    }

    public function test_execute_requires_whatsapp_otp_then_merges_pr_with_owner_note(): void
    {
        app(DeployProposalService::class)->setGithubToken('github_pat_tes123');
        $proposal = $this->notify();
        $url = route('proposals.decide', $proposal->token);

        // Tanpa OTP / OTP salah → ditolak, PR tidak di-merge.
        $this->post($url, ['action' => 'execute', 'otp' => '123456'])->assertSessionHasErrors('otp');

        $this->postJson(route('proposals.otp', $proposal->token))->assertOk();
        $code = $this->otpFromWa();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->post($url, ['action' => 'execute', 'otp' => $wrong])->assertSessionHasErrors('otp');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/merge'));

        $this->post($url, ['action' => 'execute', 'otp' => $code, 'note' => 'Mantap, besok fokus halaman produk'])->assertSessionHasNoErrors();

        $this->assertSame(DeployProposal::APPROVED, $proposal->fresh()->status);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'repos/fadlisalim/retail/pulls/12/merge') && $r->method() === 'PUT'
            && $r->hasHeader('Authorization', 'Bearer github_pat_tes123'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'issues/12/comments') && str_contains($r['body'], 'besok fokus halaman produk'));

        // Sudah diputuskan → tidak bisa diulang.
        $this->post($url, ['action' => 'execute', 'otp' => $code])->assertSessionHasErrors('action');
    }

    public function test_revision_needs_a_note_and_closes_pr(): void
    {
        app(DeployProposalService::class)->setGithubToken('github_pat_tes123');
        $proposal = $this->notify();
        $this->postJson(route('proposals.otp', $proposal->token))->assertOk();
        $code = $this->otpFromWa();

        $this->post(route('proposals.decide', $proposal->token), ['action' => 'reject', 'otp' => $code])->assertSessionHasErrors('note');
        $this->post(route('proposals.decide', $proposal->token), ['action' => 'reject', 'otp' => $code, 'note' => 'Warna tombol jangan diubah'])->assertSessionHasNoErrors();

        $this->assertSame(DeployProposal::REJECTED, $proposal->fresh()->status);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'pulls/12') && $r->method() === 'PATCH' && $r['state'] === 'closed');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/merge'));
    }

    public function test_new_proposal_supersedes_pending_one(): void
    {
        $old = $this->notify();
        $this->artisan('deploy:notify-proposal', ['sha' => str_repeat('b', 40), '--file' => $this->report(str_replace('pull/12', 'pull/13', self::REPORT))])->assertSuccessful();

        $this->assertSame(DeployProposal::SUPERSEDED, $old->fresh()->status);
        $this->assertSame(DeployProposal::PENDING, DeployProposal::where('pr_number', 13)->value('status'));
    }
}
