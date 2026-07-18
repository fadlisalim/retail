<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\WatermarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Stamps the brand watermark onto existing product gallery images.
 *
 * By default only images that have never been watermarked are processed
 * (tracked via product_images.watermarked_at), so the command is safe to
 * re-run. Use --force to re-stamp everything (e.g. after tweaking the style)
 * — note that forcing stamps on top of an already-watermarked file.
 */
class WatermarkProductImages extends Command
{
    protected $signature = 'products:watermark
        {--force : Re-stamp images that were already watermarked}
        {--sync-main : Refresh each product main_image_path to its first gallery image}';

    protected $description = 'Apply the Energi.Click watermark to product gallery images';

    public function handle(WatermarkService $watermark): int
    {
        if (! $watermark->isSupported()) {
            $this->error('Watermark tidak didukung: ekstensi GD/FreeType atau font tidak tersedia.');

            return self::FAILURE;
        }

        $query = ProductImage::query()->orderBy('id');
        if (! $this->option('force')) {
            $query->whereNull('watermarked_at');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Tidak ada gambar yang perlu diberi watermark.');

            return self::SUCCESS;
        }

        $this->info("Memproses {$total} gambar…");
        $bar = $this->output->createProgressBar($total);
        $done = 0;
        $skipped = 0;

        $query->chunkById(100, function ($images) use ($watermark, $bar, &$done, &$skipped) {
            foreach ($images as $image) {
                if (! Storage::disk('public')->exists($image->path)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $result = $watermark->apply($image->path);
                if ($result !== null) {
                    // The optimiser may re-encode to a lighter format (WebP) and
                    // return a new path; keep the DB — and any main image that
                    // pointed at the old file — in sync.
                    if ($result !== $image->path && $image->product && $image->product->main_image_path === $image->path) {
                        $image->product->update(['main_image_path' => $result]);
                    }
                    $image->forceFill(['path' => $result, 'watermarked_at' => now()])->save();
                    $done++;
                } else {
                    $skipped++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($this->option('sync-main')) {
            $this->refreshMainImages();
        }

        $this->info("Selesai. Berhasil: {$done}, dilewati: {$skipped}.");

        return self::SUCCESS;
    }

    /** Point each product's main image at its first (already-watermarked) gallery image. */
    private function refreshMainImages(): void
    {
        Product::query()->with(['images' => fn ($q) => $q->orderBy('sort_order')])->chunk(100, function ($products) {
            foreach ($products as $product) {
                $first = $product->images->first();
                if ($first && $product->main_image_path !== $first->path) {
                    $product->update(['main_image_path' => $first->path]);
                }
            }
        });

        $this->line('main_image_path disegarkan.');
    }
}
