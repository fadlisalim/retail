<?php

namespace App\Services;

use App\Models\DeployProposal;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Usulan perbaikan agent otomatis → laporan ber-link untuk pemilik → keputusan
 * ber-OTP (kode dikirim ke WA pemilik) → Eksekusi = merge PR di GitHub (lalu
 * auto-deploy menayangkan), Revisi/Tolak = tutup PR. Saran pemilik ditulis sebagai
 * komentar PR supaya dibaca agent pada putaran berikutnya.
 */
class DeployProposalService
{
    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly WhatsAppService $wa,
        private readonly SettingService $settings,
    ) {}

    public function ownerPhone(): string
    {
        return (string) config('rekasurya.deploy.report_whatsapp');
    }

    /** Daftarkan usulan dari isi deploy/PROPOSAL.md; null bila laporan belum memuat link PR. */
    public function register(string $sha, string $content): ?DeployProposal
    {
        if (! preg_match('#https://github\.com/[^/\s]+/[^/\s]+/pull/(\d+)#', $content, $pr)) {
            return null;
        }
        $report = trim(preg_replace('#^.*https://github\.com/\S+/pull/\d+.*$#m', '', $content));
        $title = preg_match('/^#\s+(.+)$/m', $report, $h) ? trim($h[1]) : 'Usulan perbaikan energi.click';

        $proposal = DeployProposal::firstOrNew(['sha' => $sha]);
        $proposal->fill(['title' => Str::limit($title, 180, ''), 'report' => $report, 'pr_url' => $pr[0], 'pr_number' => (int) $pr[1]]);
        $proposal->token ??= Str::random(48);
        $proposal->save();

        DeployProposal::where('id', '!=', $proposal->id)->where('status', DeployProposal::PENDING)
            ->update(['status' => DeployProposal::SUPERSEDED]);

        return $proposal;
    }

    /** Ringkasan untuk WA: bagian "Ringkasan" laporan (atau beberapa baris awal). */
    public function summary(DeployProposal $proposal): string
    {
        $text = preg_match('/^##\s*Ringkasan\s*$(.*?)(?=^##\s|\z)/msi', $proposal->report, $m) ? $m[1] : $proposal->report;
        $text = trim(preg_replace('/^#\s+.+$/m', '', $text));

        return Str::limit($text, 900);
    }

    public function notifyOwner(DeployProposal $proposal): bool
    {
        $message = "📋 *Laporan audit energi.click*\n*{$proposal->title}*\n\n"
            .$this->summary($proposal)
            ."\n\n👉 Baca laporan lengkap, beri saran & eksekusi (pakai OTP):\n"
            .route('proposals.show', $proposal->token)
            ."\n\nBelum tayang sampai bro menekan *Eksekusi*.";

        return $this->wa->send($this->ownerPhone(), $message);
    }

    public function sendOtp(DeployProposal $proposal): void
    {
        $this->ensurePending($proposal);
        $code = (string) random_int(100000, 999999);
        $proposal->forceFill([
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'otp_attempts' => 0,
        ])->save();

        $sent = $this->wa->send($this->ownerPhone(), "🔐 Kode OTP eksekusi usulan energi.click: *{$code}*\nBerlaku ".self::OTP_TTL_MINUTES.' menit. Jangan bagikan kode ini ke siapa pun.');
        if (! $sent) {
            throw ValidationException::withMessages(['otp' => 'OTP gagal dikirim ke WhatsApp — coba lagi sebentar.']);
        }
    }

    /** Eksekusi (merge PR) atau revisi/tolak (tutup PR), setelah OTP benar. */
    public function decide(DeployProposal $proposal, string $action, ?string $note, string $otp): void
    {
        $this->ensurePending($proposal);
        $this->verifyOtp($proposal, $otp);
        $note = trim((string) $note) ?: null;

        if ($note) {
            $this->github('post', "issues/{$proposal->pr_number}/comments", ['body' => "**Saran pemilik** ({$this->label($action)}):\n\n{$note}"]);
        }

        if ($action === 'execute') {
            $response = $this->github('put', "pulls/{$proposal->pr_number}/merge", ['merge_method' => 'squash', 'commit_title' => Str::limit($proposal->title, 70, '')]);
            if (! $response->successful()) {
                $proposal->forceFill(['error' => $response->json('message') ?: $response->body()])->save();
                throw ValidationException::withMessages(['action' => 'GitHub menolak merge: '.($response->json('message') ?: 'error '.$response->status()).'. Usulan belum tayang.']);
            }
            $status = DeployProposal::APPROVED;
        } else {
            $this->github('patch', "pulls/{$proposal->pr_number}", ['state' => 'closed']);
            $status = DeployProposal::REJECTED;
        }

        $proposal->forceFill(['status' => $status, 'owner_note' => $note, 'decided_at' => now(), 'otp_hash' => null, 'error' => null])->save();
    }

    public function githubToken(): ?string
    {
        $stored = $this->settings->get('deploy.github_token');
        if (! $stored) {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setGithubToken(?string $token): void
    {
        $this->settings->set('deploy.github_token', $token ? Crypt::encryptString($token) : '', 'string', 'deploy');
    }

    private function label(string $action): string
    {
        return $action === 'execute' ? 'dieksekusi' : 'revisi/tolak';
    }

    private function ensurePending(DeployProposal $proposal): void
    {
        if (! $proposal->isPending()) {
            throw ValidationException::withMessages(['action' => 'Usulan ini sudah tidak menunggu keputusan ('.$proposal->statusLabel().').']);
        }
        if (! $this->githubToken()) {
            throw ValidationException::withMessages(['action' => 'Token GitHub belum dipasang di Admin → Pengaturan → Status deploy otomatis.']);
        }
    }

    private function verifyOtp(DeployProposal $proposal, string $otp): void
    {
        if (! $proposal->otp_hash || ! $proposal->otp_expires_at || $proposal->otp_expires_at->isPast()) {
            throw ValidationException::withMessages(['otp' => 'OTP belum dikirim atau sudah kedaluwarsa — tekan "Kirim OTP" lagi.']);
        }
        if ($proposal->otp_attempts >= self::OTP_MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['otp' => 'Terlalu banyak percobaan — minta OTP baru.']);
        }
        if (! Hash::check(trim($otp), $proposal->otp_hash)) {
            $proposal->increment('otp_attempts');
            throw ValidationException::withMessages(['otp' => 'Kode OTP salah.']);
        }
    }

    private function github(string $method, string $path, array $payload = []): Response
    {
        return Http::withToken((string) $this->githubToken())
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(20)
            ->{$method}('https://api.github.com/repos/'.config('rekasurya.deploy.github_repo').'/'.$path, $payload);
    }
}
