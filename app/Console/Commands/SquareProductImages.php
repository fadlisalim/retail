<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\WatermarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Jadikan semua foto produk yang belum persegi menjadi 1:1 (galeri, foto utama, foto
 * varian) — sisi pendek diberi latar, produk tidak terpotong. Foto yang sudah 1:1
 * dilewati, jadi aman dijalankan ulang. Upload baru otomatis 1:1 lewat WatermarkService.
 */
class SquareProductImages extends Command
{
    protected $signature = 'products:square-images {--dry-run : Hanya hitung, tanpa mengubah}';

    protected $description = 'Jadikan foto produk yang belum persegi menjadi 1:1';

    public function handle(WatermarkService $images): int
    {
        if (! $images->isSupported()) {
            $this->error('Pengolahan gambar tidak didukung: ekstensi GD/FreeType atau font tidak tersedia.');

            return self::FAILURE;
        }

        $paths = ProductImage::whereNull('video_path')->pluck('path')
            ->merge(Product::withTrashed()->pluck('main_image_path'))
            ->merge(ProductVariant::pluck('image_path'))
            ->filter()->unique()->values();

        $done = $skipped = $failed = 0;
        foreach ($paths as $path) {
            if (! Storage::disk('public')->exists($path) || $images->isSquare($path) !== false) {
                $skipped++;

                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("  {$path}");
                $done++;

                continue;
            }

            $new = $images->square($path);
            if (! $new) {
                $failed++;

                continue;
            }
            ProductImage::where('path', $path)->update(['path' => $new]);
            Product::withTrashed()->where('main_image_path', $path)->update(['main_image_path' => $new]);
            ProductVariant::where('image_path', $path)->update(['image_path' => $new]);
            $done++;
        }

        $verb = $this->option('dry-run') ? 'perlu dijadikan' : 'dijadikan';
        $this->info("Foto produk: {$done} {$verb} 1:1, {$skipped} sudah persegi/tidak ada, {$failed} gagal.");

        return self::SUCCESS;
    }
}
