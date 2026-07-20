<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Converts stored phone numbers to international format (08… → 62…) so WhatsApp
 * (Wablas) can reach existing customers & affiliates saved before the country-code
 * phone picker. Only changes numbers that actually need it; numbers already in
 * international format (62…, 60…, …) are left untouched. Idempotent + --dry-run.
 */
class NormalizePhones extends Command
{
    protected $signature = 'users:normalize-phones {--dry-run : Tampilkan yang akan diubah tanpa menyimpan}';

    protected $description = 'Normalisasi nomor telepon customer & afiliator ke format internasional (08… → 62…)';

    public function handle(WhatsAppService $wa): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = 0;
        $scanned = 0;

        // Users: whatsapp + phone.
        User::withTrashed()->chunkById(200, function ($users) use ($wa, $dry, &$changed, &$scanned) {
            foreach ($users as $user) {
                $dirty = false;
                foreach (['whatsapp', 'phone'] as $field) {
                    $scanned++;
                    $orig = $user->{$field};
                    $norm = $wa->normalize($orig);
                    if ($norm && $norm !== $orig) {
                        if ($dry) {
                            $this->line("User #{$user->id} {$field}: {$orig} → {$norm}");
                        } else {
                            $user->{$field} = $norm;
                            $dirty = true;
                        }
                        $changed++;
                    }
                }
                if ($dirty) {
                    $user->saveQuietly();
                }
            }
        });

        // Affiliates: contact phone.
        Affiliate::query()->chunkById(200, function ($affiliates) use ($wa, $dry, &$changed, &$scanned) {
            foreach ($affiliates as $aff) {
                $scanned++;
                $orig = $aff->phone;
                $norm = $wa->normalize($orig);
                if ($norm && $norm !== $orig) {
                    if ($dry) {
                        $this->line("Afiliator #{$aff->id} phone: {$orig} → {$norm}");
                    } else {
                        $aff->phone = $norm;
                        $aff->saveQuietly();
                    }
                    $changed++;
                }
            }
        });

        $this->newLine();
        $this->info(($dry ? '[dry-run] ' : '')."Selesai. Diperiksa: {$scanned} nomor, ".($dry ? 'akan diubah' : 'diubah').": {$changed}.");

        return self::SUCCESS;
    }
}
