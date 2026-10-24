<?php

namespace App\Console\Commands;

use App\Services\DeployProposalService;
use Illuminate\Console\Command;

/**
 * Dipanggil deploy/auto-deploy.sh saat branch usulan agent (claude/auto-improve)
 * punya commit baru yang belum tayang: daftarkan laporan (deploy/PROPOSAL.md, disalin
 * skrip ke --file) dan kirim link halaman laporan ber-OTP ke WhatsApp pemilik.
 *
 * Exit gagal = belum siap (laporan belum memuat link PR) atau WA gagal → dicoba lagi.
 */
class NotifyDeployProposal extends Command
{
    protected $signature = 'deploy:notify-proposal {sha : Commit usulan} {--file= : Isi deploy/PROPOSAL.md pada commit itu}';

    protected $description = 'Daftarkan usulan perbaikan agent & kirim link laporannya ke WhatsApp pemilik';

    public function handle(DeployProposalService $proposals): int
    {
        $sha = (string) $this->argument('sha');
        if (! preg_match('/^[0-9a-f]{40}$/', $sha)) {
            $this->error('SHA tidak valid.');

            return self::FAILURE;
        }

        $file = (string) $this->option('file');
        $proposal = $proposals->register($sha, $file !== '' && is_file($file) ? (string) file_get_contents($file) : '');
        if (! $proposal) {
            return self::FAILURE; // laporan/PR belum siap — dicoba lagi pada putaran berikutnya
        }

        if (! $proposals->notifyOwner($proposal)) {
            $this->error('Laporan usulan gagal dikirim ke WA pemilik.');

            return self::FAILURE;
        }

        $this->info('Laporan usulan '.substr($sha, 0, 7).' terkirim ke WA pemilik.');

        return self::SUCCESS;
    }
}
