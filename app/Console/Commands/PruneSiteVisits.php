<?php

namespace App\Console\Commands;

use App\Models\SiteVisit;
use Illuminate\Console\Command;

/**
 * Retention for the traffic log: visits older than the configured window
 * (default ~3 months) are deleted. Scheduled daily in routes/console.php.
 */
class PruneSiteVisits extends Command
{
    protected $signature = 'visits:prune';

    protected $description = 'Hapus log kunjungan website yang lebih tua dari masa simpan (default 90 hari)';

    public function handle(): int
    {
        $days = max(1, (int) config('rekasurya.traffic_retention_days', 90));
        $cutoff = now()->subDays($days);

        $deleted = SiteVisit::where('created_at', '<', $cutoff)->delete();

        $this->info("Menghapus {$deleted} kunjungan lebih tua dari {$days} hari (sebelum {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}
