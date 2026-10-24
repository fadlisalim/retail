<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Dipanggil deploy/auto-deploy.sh saat branch usulan agent (claude/auto-improve)
 * punya commit baru yang belum tayang: kirim isi deploy/PROPOSAL.md (disalin skrip
 * ke --file) + link PR ke WhatsApp pemilik. Usulan tayang setelah PR di-merge.
 *
 * Exit gagal = belum siap (laporan belum memuat link PR) atau WA gagal → dicoba lagi.
 */
class NotifyDeployProposal extends Command
{
    protected $signature = 'deploy:notify-proposal {sha : Commit usulan} {--file= : Isi deploy/PROPOSAL.md pada commit itu}';

    protected $description = 'Kirim laporan usulan perbaikan agent + link PR ke WhatsApp pemilik';

    public function handle(WhatsAppService $wa): int
    {
        $sha = (string) $this->argument('sha');
        if (! preg_match('/^[0-9a-f]{7,40}$/', $sha)) {
            $this->error('SHA tidak valid.');

            return self::FAILURE;
        }

        $file = (string) $this->option('file');
        $content = $file !== '' && is_file($file) ? (string) file_get_contents($file) : '';
        if (! preg_match('#https://github\.com/\S+/pull/\d+#', $content, $pr)) {
            return self::FAILURE; // laporan/PR belum siap — dicoba lagi pada putaran berikutnya
        }

        $report = trim(preg_replace('#^.*https://github\.com/\S+/pull/\d+.*$#m', '', $content));
        $message = "🛠️ *Usulan perbaikan energi.click*\n\n"
            .Str::limit($report, 3000)
            ."\n\n👉 Cek detail & setujui (tombol *Merge*):\n{$pr[0]}\n\n"
            .'Setelah di-merge, otomatis tayang ±2 menit. Tidak setuju? Abaikan / tutup PR-nya.';

        if (! $wa->send((string) config('rekasurya.deploy.report_whatsapp'), $message)) {
            $this->error('Laporan usulan gagal dikirim ke WA: '.($wa->lastResult['body'] ?? '-'));

            return self::FAILURE;
        }

        $this->info('Laporan usulan '.substr($sha, 0, 7).' terkirim ke WA pemilik.');

        return self::SUCCESS;
    }
}
