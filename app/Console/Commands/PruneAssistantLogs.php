<?php

namespace App\Console\Commands;

use App\Models\AssistantConversation;
use App\Models\AssistantDailyStat;
use App\Models\AssistantDailyTerm;
use Illuminate\Console\Command;

/**
 * Retention for CS-assistant logs. Raw transcripts (which may contain personal
 * data a customer typed) are deleted after a short window; the non-personal
 * daily rollups are kept longer so the admin dashboard retains trends. Runs on
 * the scheduler (see routes/console.php).
 */
class PruneAssistantLogs extends Command
{
    protected $signature = 'assistant:prune {--dry-run : Show what would be deleted without changing anything}';

    protected $description = 'Hapus transkrip CS lama & rollup statistik yang kedaluwarsa';

    public function handle(): int
    {
        $logDays = max(1, (int) config('services.anthropic.log_retention_days', 30));
        $statsDays = max($logDays, (int) config('services.anthropic.stats_retention_days', 180));
        $dry = (bool) $this->option('dry-run');

        $logCutoff = now()->subDays($logDays);
        $statsCutoff = now()->subDays($statsDays)->toDateString();

        $conversations = AssistantConversation::where('created_at', '<', $logCutoff);
        $stats = AssistantDailyStat::whereDate('day', '<', $statsCutoff);
        $terms = AssistantDailyTerm::whereDate('day', '<', $statsCutoff);

        if ($dry) {
            $this->info("Transkrip > {$logDays} hari: ".$conversations->count());
            $this->info("Statistik harian > {$statsDays} hari: ".($stats->count() + $terms->count()));

            return self::SUCCESS;
        }

        $deletedLogs = $conversations->delete();
        $deletedStats = $stats->delete() + $terms->delete();

        $this->info("Hapus {$deletedLogs} transkrip (> {$logDays} hari) & {$deletedStats} baris statistik (> {$statsDays} hari).");

        return self::SUCCESS;
    }
}
