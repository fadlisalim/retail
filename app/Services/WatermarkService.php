<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Stamps a repeated, diagonal "energi.click" watermark across an image using GD.
 *
 * The pattern is tiled at low opacity with a subtle dark shadow so the brand
 * mark stays legible on both light and dark product photos while protecting the
 * image from casual reuse. Operates in place on the stored file.
 */
class WatermarkService
{
    /** Path to the bundled font (portable across dev/production). */
    private string $fontPath;

    public function __construct()
    {
        $this->fontPath = resource_path('fonts/LiberationSans-Bold.ttf');
    }

    public function isSupported(): bool
    {
        return \function_exists('imagettftext') && \function_exists('imagecreatetruecolor') && is_file($this->fontPath);
    }

    /**
     * Watermark a stored file in place. Returns true on success.
     */
    public function apply(string $path, string $disk = 'public', ?string $text = null): bool
    {
        if (! $this->isSupported()) {
            return false;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return false;
        }

        $full = $storage->path($path);
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        $img = $this->load($full, $ext);
        if (! $img) {
            return false;
        }

        $this->stampTiled($img, $text ?? (string) config('rekasurya.company.brand_name', 'Energi.Click'));

        $ok = $this->save($img, $full, $ext);
        imagedestroy($img);

        return $ok;
    }

    private function load(string $full, string $ext): \GdImage|false
    {
        $img = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($full),
            'png' => @imagecreatefrompng($full),
            'webp' => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($full) : false,
            default => false,
        };

        if ($img instanceof \GdImage) {
            imagealphablending($img, true);
        }

        return $img ?: false;
    }

    private function save(\GdImage $img, string $full, string $ext): bool
    {
        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($img, $full, 88),
            'png' => imagepng($img, $full),
            'webp' => \function_exists('imagewebp') ? imagewebp($img, $full, 88) : false,
            default => false,
        };
    }

    /**
     * Draw the brand text repeatedly on a diagonal grid across the whole image.
     */
    private function stampTiled(\GdImage $img, string $text): void
    {
        $w = imagesx($img);
        $h = imagesy($img);

        // Scale font to the image so small and large photos look consistent.
        $fontSize = max(11, (int) round(min($w, $h) * 0.038));
        $angle = 30;

        // Very light, barely-there mark. GD alpha: 0 = opaque, 127 = transparent.
        $white = imagecolorallocatealpha($img, 255, 255, 255, 112);
        $shadow = imagecolorallocatealpha($img, 0, 0, 0, 118);

        // Measure one label to space the tiling grid.
        $box = imagettfbbox($fontSize, $angle, $this->fontPath, $text);
        $textW = abs($box[2] - $box[0]);
        $textH = abs($box[7] - $box[1]);

        // Sparse spacing — plenty of breathing room between marks.
        $stepX = max(240, (int) ($textW + $fontSize * 9));
        $stepY = max(220, (int) ($textH + $fontSize * 11));

        // Start off-canvas so the pattern fills edge to edge even with rotation.
        for ($y = -$stepY; $y < $h + $stepY; $y += $stepY) {
            // Offset every other row for a masonry-like, harder-to-crop pattern.
            $rowOffset = (($y / $stepY) % 2 === 0) ? 0 : (int) ($stepX / 2);
            for ($x = -$stepX; $x < $w + $stepX; $x += $stepX) {
                $px = $x + $rowOffset;
                imagettftext($img, $fontSize, $angle, $px + 2, $y + 2, $shadow, $this->fontPath, $text);
                imagettftext($img, $fontSize, $angle, $px, $y, $white, $this->fontPath, $text);
            }
        }
    }
}
