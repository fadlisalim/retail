<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\WatermarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Re-optimises EXISTING product images (gallery, variant, and main images) by
 * downscaling and re-encoding them to a lighter format (WebP) — WITHOUT adding
 * another watermark. Fixes the legacy heavy PNG uploads (~2 MB) that ballooned
 * page weight, and remaps every database reference to the new file path.
 *
 * Safe to re-run: files already at the optimal size/format simply shrink a
 * little or stay the same, and paths are only updated when they actually change.
 */
class OptimizeProductImages extends Command
{
    protected $signature = 'products:optimize {--dry-run : Report savings without writing changes}';

    protected $description = 'Downscale + re-encode existing product images to a lighter format (no re-watermark)';

    public function handle(WatermarkService $watermark): int
    {
        if (! $watermark->isSupported()) {
            $this->error('Optimasi tidak didukung: ekstensi GD tidak tersedia.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        // Every distinct file referenced anywhere in the catalogue.
        $paths = ProductImage::query()->pluck('path')
            ->merge(ProductVariant::query()->whereNotNull('image_path')->pluck('image_path'))
            ->merge(Product::query()->whereNotNull('main_image_path')->pluck('main_image_path'))
            ->filter()
            ->unique()
            ->values();

        if ($paths->isEmpty()) {
            $this->info('Tidak ada gambar untuk dioptimasi.');

            return self::SUCCESS;
        }

        $this->info(($dry ? '[dry-run] ' : '').'Memproses '.$paths->count().' gambar…');
        $bar = $this->output->createProgressBar($paths->count());

        $bytesBefore = 0;
        $bytesAfter = 0;
        $done = 0;
        $skipped = 0;
        $map = [];   // old path => new path (when it changed)

        $disk = Storage::disk('public');

        foreach ($paths as $old) {
            if (! $disk->exists($old)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $sizeBefore = $disk->size($old);

            if ($dry) {
                // Estimate only: don't touch the file.
                $bytesBefore += $sizeBefore;
                $bar->advance();

                continue;
            }

            $new = $watermark->optimize($old);
            if ($new === null) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $bytesBefore += $sizeBefore;
            $bytesAfter += $disk->exists($new) ? $disk->size($new) : $sizeBefore;

            if ($new !== $old) {
                $map[$old] = $new;
            }
            $done++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dry) {
            $this->line('Total ukuran saat ini: '.$this->human($bytesBefore).' ('.$paths->count().' file).');
            $this->line('Jalankan tanpa --dry-run untuk mengoptimasi.');

            return self::SUCCESS;
        }

        // Remap every DB reference whose file was re-encoded to a new path.
        foreach ($map as $old => $new) {
            ProductImage::where('path', $old)->update(['path' => $new]);
            ProductVariant::where('image_path', $old)->update(['image_path' => $new]);
            Product::where('main_image_path', $old)->update(['main_image_path' => $new]);
        }

        $saved = max(0, $bytesBefore - $bytesAfter);
        $this->info("Selesai. Dioptimasi: {$done}, dilewati: {$skipped}.");
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
