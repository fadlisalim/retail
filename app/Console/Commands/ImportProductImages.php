<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\WatermarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bulk-imports product gallery images from a staging folder, matched to products
 * by slug. Drop the official supplier images into storage/app/product-imports/
 * either as a single file per product (`{slug}.jpg`) or a folder of images
 * (`{slug}/1.jpg`, `{slug}/2.jpg`, …), then run this command. Each image is
 * optimised + watermarked through WatermarkService (same pipeline as admin
 * uploads) and the first becomes the product's main image.
 *
 *   php artisan products:import-images
 *   php artisan products:import-images --slug=bluetti-apex-300 --force
 */
class ImportProductImages extends Command
{
    protected $signature = 'products:import-images
        {--path=product-imports : Source folder on the local disk (storage/app/…)}
        {--slug= : Only import for this product slug}
        {--force : Import even if the product already has images}
        {--no-watermark : Skip the watermark/optimise pass (store as-is)}';

    protected $description = 'Bulk-import product images from a staging folder, matched by slug';

    private const EXT = ['jpg', 'jpeg', 'png', 'webp'];

    public function handle(WatermarkService $watermark): int
    {
        $base = rtrim($this->option('path'), '/');
        $local = Storage::disk('local');

        if (! $local->exists($base)) {
            $this->error('Folder sumber tidak ditemukan: '.$local->path($base));
            $this->line('Buat folder itu, taruh gambar (nama file/folder = slug produk), lalu jalankan lagi.');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $missing = [];

        // Build the work-list: slug => [absolute source paths].
        $groups = $this->collect($local, $base);

        if (empty($groups)) {
            $this->warn('Tidak ada gambar di '.$local->path($base).'. Nama file/folder harus = slug produk.');

            return self::SUCCESS;
        }

        foreach ($groups as $slug => $sources) {
            if ($this->option('slug') && $this->option('slug') !== $slug) {
                continue;
            }

            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                $missing[] = $slug;

                continue;
            }

            if ($product->images()->exists() && ! $this->option('force')) {
                $this->line("• {$slug}: sudah punya gambar, dilewati (pakai --force untuk timpa).");
                $skipped++;

                continue;
            }

            $next = (int) ($product->images()->max('sort_order') ?? 0);
            sort($sources);
            foreach ($sources as $abs) {
                $stored = 'products/'.$slug.'-'.($next + 1).'.'.strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                Storage::disk('public')->put($stored, (string) file_get_contents($abs));

                $finalPath = $stored;
                $watermarked = null;
                if (! $this->option('no-watermark')) {
                    $result = $watermark->apply($stored);
                    if ($result) {
                        $finalPath = $result;
                        $watermarked = now();
                    }
                }

                $product->images()->create([
                    'path' => $finalPath,
                    'alt' => $product->name,
                    'sort_order' => ++$next,
                    'watermarked_at' => $watermarked,
                ]);
                $imported++;
            }

            // First image becomes the main image (or when forcing a refresh).
            $first = $product->images()->orderBy('sort_order')->value('path');
            if ($first && (! $product->main_image_path || $this->option('force'))) {
                $product->update(['main_image_path' => $first]);
            }

            $this->info("✓ {$slug}: ".count($sources).' gambar diimpor.');
        }

        if ($missing) {
            $this->warn('Slug tidak cocok dengan produk mana pun: '.implode(', ', $missing));
        }

        $this->newLine();
        $this->info("Selesai. {$imported} gambar diimpor, {$skipped} produk dilewati.");

        return self::SUCCESS;
    }

    /**
     * Map slug => [source files]. Supports `{slug}.ext` (single) and `{slug}/…`
     * (folder of images).
     *
     * @return array<string, list<string>>
     */
    private function collect(\Illuminate\Contracts\Filesystem\Filesystem $local, string $base): array
    {
        $groups = [];

        // Single file per product: product-imports/{slug}.jpg
        foreach ($local->files($base) as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($ext, self::EXT, true)) {
                continue;
            }
            $slug = pathinfo($file, PATHINFO_FILENAME);
            $groups[$slug][] = $local->path($file);
        }

        // Folder per product: product-imports/{slug}/1.jpg, 2.jpg…
        foreach ($local->directories($base) as $dir) {
            $slug = basename($dir);
            foreach ($local->files($dir) as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, self::EXT, true)) {
                    $groups[$slug][] = $local->path($file);
                }
            }
        }

        return $groups;
    }
}
