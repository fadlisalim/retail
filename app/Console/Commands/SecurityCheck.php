<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Pemeriksaan keamanan server yang bisa dijalankan kapan saja
 * (php artisan security:check). Dibuat setelah insiden token Wablas bocor:
 * kode aplikasi tidak pernah menampilkan/mencatat token, jadi kebocoran
 * hampir pasti dari sisi server — file .env yang bisa diunduh, salinan
 * .env.save/.env.bak, riwayat shell, izin file, debug mode, atau akun/
 * panel hosting. Perintah ini memeriksa yang bisa dicek dari dalam aplikasi.
 */
class SecurityCheck extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Periksa konfigurasi & file server yang rawan membocorkan rahasia (.env, debug, webhook, izin file)';

    /** @var list<array{0: string, 1: string, 2: string}> [status, judul, detail] */
    private array $rows = [];

    public function handle(): int
    {
        $this->checkEnvironment();
        $this->checkSecretsConfigured();
        $this->checkEnvFiles();
        $this->checkPublicDir();
        $this->checkShellHistory();

        $this->table(['', 'Pemeriksaan', 'Keterangan'], $this->rows);

        $bad = count(array_filter($this->rows, fn ($r) => $r[0] === 'GAWAT'));
        $warn = count(array_filter($this->rows, fn ($r) => $r[0] === 'CEK'));

        if ($bad) {
            $this->error("{$bad} temuan GAWAT — perbaiki sekarang, lalu ganti (regenerate) token Wablas & rahasia lain di .env.");
        } elseif ($warn) {
            $this->warn("{$warn} hal perlu dicek manual.");
        } else {
            $this->info('Tidak ada temuan dari dalam aplikasi. Sisanya cek di luar aplikasi: password/2FA akun Wablas, update CyberPanel, akses SSH/FTP.');
        }

        $this->line('');
        $this->line('Cek dari luar (jalankan di laptop): curl -sI https://'.parse_url((string) config('app.url'), PHP_URL_HOST).'/.env  → harus 404/403, BUKAN 200.');

        return $bad ? self::FAILURE : self::SUCCESS;
    }

    private function checkEnvironment(): void
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');

        $this->add(! $debug ? 'OK' : ($env === 'production' ? 'GAWAT' : 'CEK'), 'APP_DEBUG',
            $debug ? 'AKTIF — halaman error bisa membocorkan konfigurasi & rahasia. Set APP_DEBUG=false.' : 'nonaktif');
        $this->add($env === 'production' ? 'OK' : 'CEK', 'APP_ENV', $env.($env !== 'production' ? ' — server live harus "production" (pemeriksaan webhook & cookie bergantung ini)' : ''));
        $this->add(config('session.secure') ? 'OK' : 'CEK', 'SESSION_SECURE_COOKIE',
            config('session.secure') ? 'true' : 'belum true — cookie login bisa terkirim tanpa HTTPS');
        $this->add(trim((string) env('TRUSTED_PROXIES', '')) !== '' ? 'OK' : 'CEK', 'TRUSTED_PROXIES',
            trim((string) env('TRUSTED_PROXIES', '')) !== '' ? 'terisi' : 'kosong — di belakang Cloudflare/LiteSpeed, rate limit & IP log memakai IP proxy');
    }

    private function checkSecretsConfigured(): void
    {
        $webhook = (string) config('services.wablas.webhook_token');
        $wablasOn = (bool) config('services.wablas.enabled');

        $this->add(! $wablasOn || $webhook !== '' ? 'OK' : 'GAWAT', 'WABLAS_WEBHOOK_TOKEN',
            $webhook !== '' ? 'terisi ('.strlen($webhook).' karakter)' : ($wablasOn ? 'KOSONG — siapa pun bisa mengirim pesan masuk palsu ke inbox WA Chat' : 'Wablas nonaktif'));
        if ($webhook !== '' && strlen($webhook) < 16) {
            $this->add('CEK', 'WABLAS_WEBHOOK_TOKEN', 'pendek (< 16 karakter) — mudah ditebak, ganti dengan yang panjang & acak');
        }

        $this->add(filled(config('app.key')) ? 'OK' : 'GAWAT', 'APP_KEY', filled(config('app.key')) ? 'terisi' : 'kosong');

        $token = (string) config('services.wablas.token');
        $this->add($wablasOn && $token === '' ? 'CEK' : 'OK', 'WABLAS_TOKEN',
            $token === '' ? 'kosong' : 'terisi (tidak ditampilkan). Setelah insiden: regenerate di dashboard Wablas, ganti di .env, php artisan config:clear');
    }

    private function checkEnvFiles(): void
    {
        $base = base_path();
        $env = $base.'/.env';

        if (is_file($env)) {
            $perms = substr(sprintf('%o', fileperms($env)), -3);
            $worldReadable = ((int) $perms[2]) & 4;
            $this->add($worldReadable ? 'GAWAT' : 'OK', 'Izin file .env',
                $perms.($worldReadable ? ' — bisa dibaca user lain di server. Jalankan: chmod 600 '.$env : ''));
        } else {
            $this->add('CEK', 'File .env', 'tidak ditemukan di '.$base);
        }

        // Salinan .env (.env.save, .env.bak, .env.backup, .env.old, .env~) berisi
        // rahasia yang sama tapi luput dari perhatian — hapus setelah dipakai.
        $copies = array_filter(File::glob($base.'/.env*'), fn ($f) => ! in_array(basename($f), ['.env', '.env.example'], true));
        foreach ($copies as $file) {
            $this->add('GAWAT', 'Salinan .env', basename($file).' — berisi rahasia yang sama. Hapus: rm '.$file);
        }
        if (! $copies) {
            $this->add('OK', 'Salinan .env', 'tidak ada .env.save / .env.bak / .env.backup');
        }
    }

    private function checkPublicDir(): void
    {
        $public = public_path();

        // Apa pun di public/ bisa diunduh siapa saja.
        foreach (['.env', '.env.save', '.env.bak', '.env.backup', '.git', 'storage/logs', 'phpinfo.php', 'info.php', 'adminer.php'] as $name) {
            if (file_exists($public.'/'.$name)) {
                $this->add('GAWAT', 'File di public/', $name.' bisa diunduh publik — hapus: rm -rf '.$public.'/'.$name);
            }
        }

        // Dump database / arsip di public.
        $dumps = array_merge(File::glob($public.'/*.sql'), File::glob($public.'/*.sql.gz'), File::glob($public.'/*.zip'), File::glob($public.'/*.tar.gz'));
        foreach ($dumps as $dump) {
            $this->add('GAWAT', 'File di public/', basename($dump).' bisa diunduh publik — pindahkan/hapus');
        }

        // Document root harus public/, bukan root repo (kalau tidak, .env & .git terbuka).
        $docroot = getenv('DOCUMENT_ROOT') ?: '';
        if ($docroot !== '') {
            $ok = realpath($docroot) === realpath($public);
            $this->add($ok ? 'OK' : 'GAWAT', 'Document root', $docroot.($ok ? '' : ' — HARUS mengarah ke '.$public.' (sekarang seluruh repo termasuk .env bisa diunduh)'));
        } else {
            $this->add('CEK', 'Document root', 'tidak terdeteksi dari CLI — pastikan di CyberPanel document root = '.$public.' (bukan folder repo)');
        }

        $this->add('OK', 'File di public/', 'tidak ada .env / .git / dump database di public/');
    }

    private function checkShellHistory(): void
    {
        $home = getenv('HOME') ?: '';
        foreach (['/.bash_history', '/.zsh_history'] as $hist) {
            $file = $home.$hist;
            if ($home === '' || ! is_readable($file)) {
                continue;
            }
            $content = (string) @file_get_contents($file);
            if (preg_match('/(WABLAS_TOKEN|ANTHROPIC_API_KEY|RAJAONGKIR_API_KEY|DB_PASSWORD|MAIL_PASSWORD)\s*=\s*\S{8,}/', $content)) {
                $this->add('GAWAT', 'Riwayat shell', basename($file).' memuat rahasia yang pernah diketik di terminal — bersihkan: history -c && > '.$file);
            }
        }
    }

    private function add(string $status, string $title, string $detail): void
    {
        $this->rows[] = [$status, $title, $detail];
    }
}
