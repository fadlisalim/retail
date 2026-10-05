<?php

namespace App\Console\Commands;

use App\Services\WaCampaign\WaCampaignService;
use Illuminate\Console\Command;

/** Dispatcher antrean WA Campaign — dijalankan scheduler tiap menit (lihat routes/console.php). */
class WaCampaignDispatch extends Command
{
    protected $signature = 'wa-campaign:dispatch {--limit= : Maks. pesan pada putaran ini (default: per_minute dari Pengaturan)}';

    protected $description = 'Kirim pesan WA Campaign yang antre sesuai jam kirim, rate, kuota harian, dan izin kontak';

    public function handle(WaCampaignService $service): int
    {
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $r = $service->dispatch($limit);
        $reason = match ($r['reason']) {
            'emergency_stop' => 'EMERGENCY STOP aktif — tidak ada yang dikirim.',
            'outside_window' => 'Di luar jam kirim.',
            'daily_limit' => 'Kuota harian tercapai.',
            'device_disconnected' => 'Device Wablas tidak terhubung — campaign dijeda.',
            'locked' => 'Putaran sebelumnya masih berjalan.',
            default => null,
        };
        $this->info("WA Campaign: terkirim {$r['sent']}, dilewati {$r['skipped']}, gagal {$r['failed']}, tidak pasti {$r['uncertain']}.".($reason ? ' '.$reason : ''));

        return self::SUCCESS;
    }
}
