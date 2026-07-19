<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Sends a test WhatsApp message through Wablas to verify the integration on a
 * live server (credentials, base_url, and outbound network).
 *
 *   php artisan wa:test 08123456789
 */
class WhatsAppTest extends Command
{
    protected $signature = 'wa:test {phone : Nomor tujuan (mis. 08123456789)}';

    protected $description = 'Kirim pesan WhatsApp uji melalui Wablas';

    public function handle(WhatsAppService $wa): int
    {
        if (! $wa->isEnabled()) {
            $this->error('Wablas belum aktif. Set WABLAS_ENABLED=true & WABLAS_TOKEN di .env, lalu `php artisan config:clear`.');

            return self::FAILURE;
        }

        $phone = $this->argument('phone');
        $this->line('Server  : '.config('services.wablas.base_url'));
        $this->line('Tujuan  : '.($wa->normalize($phone) ?? 'INVALID').' (dari input "'.$phone.'")');

        $ok = $wa->send($phone, "Tes notifikasi WhatsApp dari ".brand().". Integrasi Wablas berhasil ✅");

        // Show the raw Wablas response so failures are diagnosable at a glance.
        if ($wa->lastResult) {
            $this->line('HTTP    : '.($wa->lastResult['status'] ?? '-'));
            $this->line('Respons : '.$wa->lastResult['body']);
        }

        if ($ok) {
            $this->info('Terkirim. Cek WhatsApp nomor tujuan.');

            return self::SUCCESS;
        }

        $this->error('Gagal mengirim. Baca "Respons" di atas: biasanya token/secret salah, device belum "connected" di dashboard Wablas, atau base_url beda server.');

        return self::FAILURE;
    }
}
