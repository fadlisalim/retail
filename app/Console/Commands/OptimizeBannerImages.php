<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Services\WatermarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-optimises EXISTING banner images (desktop + mobile) by downscaling and
 * re-encoding to WebP — no watermark. Fixes heavy legacy PNG banners that slow
 * the homepage, and remaps each banner's image path to the new file.
 *
 * Safe to re-run (already-optimal files just stay light).
 */
class OptimizeBannerImages extends Command
{
    protected $signature = 'banners:optimize {--dry-run : Report sizes without writing changes}';

    protected $description = 'Downscale + re-encode existing banner images to WebP (no watermark)';

    public function handle(WatermarkService $watermark): int
    {
        if (! $watermark->isSupported()) {
            $this->error('Optimasi tidak didukung: ekstensi GD tidak tersedia.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $banners = Banner::query()->get();
        $bytesBefore = 0;
        $bytesAfter = 0;
        $done = 0;

        foreach ($banners as $banner) {
            foreach (['image_desktop_path', 'image_mobile_path'] as $column) {
                $path = $banner->{$column};
                if (! $path || ! $disk->exists($path)) {
                    continue;
                }

                $before = $disk->size($path);
                $bytesBefore += $before;

                if ($dry) {
                    continue;
                }

                $new = $watermark->optimize($path);
                if ($new === null) {
                    $bytesAfter += $before;

                    continue;
                }

                $bytesAfter += $disk->exists($new) ? $disk->size($new) : $before;
                if ($new !== $path) {
                    $banner->{$column} = $new;
                }
                $done++;
            }

            if (! $dry && $banner->isDirty()) {
                $banner->save();
            }
        }

        $this->newLine();
        if ($dry) {
            $this->line('Total ukuran banner saat ini: '.$this->human($bytesBefore).'. Jalankan tanpa --dry-run untuk optimasi.');

            return self::SUCCESS;
        }

        $saved = max(0, $bytesBefore - $bytesAfter);
        $this->info("Selesai. {$done} gambar banner dioptimasi.");
        $this->line('Ukuran: '.$this->human($bytesBefore).' → '.$this->human($bytesAfter).' (hemat '.$this->human($saved).').');

        return self::SUCCESS;
    }

    private function human(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024).' KB';
        }

        return $bytes.' B';
    }
}
