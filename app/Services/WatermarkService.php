<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Optimises and watermarks an image using GD, in a single re-encode pass:
 *  1. downscale oversized images to an optimum max dimension (keeps aspect ratio),
 *  2. stamp a light, tiled diagonal "energi.click" watermark,
 *  3. re-encode as WebP (much smaller than PNG/JPEG at the same quality, and it
 *     keeps transparency) so uploaded photos stay light and sharp.
 *
 * The output may use a different extension than the source (e.g. a heavy PNG
 * photo becomes a small .webp), so {@see apply()} returns the FINAL storage path.
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
     * Optimise + watermark a stored image. May re-encode to a smaller format
     * (WebP) and therefore change the file extension; the original is deleted
     * when that happens. Returns the FINAL storage path on success, or null on
     * failure (in which case the original file is left untouched).
     */
    public function apply(string $path, string $disk = 'public', ?string $text = null): ?string
    {
        return $this->reencode($path, $disk, function (\GdImage $img) use ($text): void {
            $this->stampTiled($img, $text ?? (string) config('rekasurya.company.brand_name', 'Energi.Click'));
        });
    }

    /**
     * Optimise an EXISTING stored image (downscale + re-encode to a lighter
     * format) WITHOUT adding a watermark — used to shrink already-watermarked
     * files in bulk. Returns the final path, or null on failure.
     */
    public function optimize(string $path, string $disk = 'public'): ?string
    {
        return $this->reencode($path, $disk, null);
    }

    /**
     * Shared re-encode pipeline: load → downscale → (optional mutate) → encode
     * to the most efficient format. Returns the FINAL storage path (extension
     * may change), deleting the original when it does, or null on failure.
     */
    private function reencode(string $path, string $disk, ?\Closure $mutate): ?string
    {
        if (! $this->isSupported()) {
            return null;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        $full = $storage->path($path);
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        $img = $this->load($full, $ext);
        if (! $img) {
            return null;
        }

        $img = $this->downscale($img);
        $hasAlpha = $this->hasAlpha($img, $ext);

        if ($mutate) {
            $mutate($img);
        }

        // Pick the most efficient encoder for this environment/content.
        [$newExt, $encoder] = $this->chooseFormat($hasAlpha);

        $newPath = $newExt === $ext ? $path : preg_replace('/\.[^.]+$/', '.'.$newExt, $path);
        $newFull = $storage->path($newPath);

        // Keep transparency through the final encode.
        imagealphablending($img, false);
        imagesavealpha($img, true);

        $ok = $encoder($img, $newFull);
        imagedestroy($img);

        if (! $ok) {
            return null;
        }

        // Drop the original when the re-encode changed the extension (png -> webp).
        if ($newPath !== $path && $storage->exists($path)) {
            $storage->delete($path);
        }

        return $newPath;
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

    /**
     * Choose the output format + encoder. WebP is preferred everywhere it's
     * available (best size, keeps alpha). Otherwise fall back to PNG for images
     * with transparency and JPEG for opaque photos.
     *
     * @return array{0: string, 1: callable(\GdImage, string): bool}
     */
    private function chooseFormat(bool $hasAlpha): array
    {
        $quality = (int) config('rekasurya.media.image_quality', 80);

        if (\function_exists('imagewebp')) {
            return ['webp', fn (\GdImage $img, string $f): bool => imagewebp($img, $f, $quality)];
        }

        if ($hasAlpha) {
            return ['png', fn (\GdImage $img, string $f): bool => imagepng($img, $f, 6)];
        }

        return ['jpg', fn (\GdImage $img, string $f): bool => imagejpeg($img, $f, max(78, $quality + 2))];
    }

    /**
     * Sample the image for any semi/fully transparent pixels. Only PNG/WebP
     * sources can carry an alpha channel, so JPEG short-circuits to false.
     */
    private function hasAlpha(\GdImage $img, string $ext): bool
    {
        if (! \in_array($ext, ['png', 'webp'], true)) {
            return false;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $stepX = max(1, (int) ($w / 64));
        $stepY = max(1, (int) ($h / 64));

        for ($y = 0; $y < $h; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Downscale an oversized image to the configured max dimension (longest side),
     * preserving aspect ratio and transparency. Returns the original when small
     * enough (never upscales).
     */
    private function downscale(\GdImage $img): \GdImage
    {
        $max = (int) config('rekasurya.media.max_image_dimension', 1600);
        $w = imagesx($img);
        $h = imagesy($img);
        $longest = max($w, $h);

        if ($max <= 0 || $longest <= $max) {
            return $img;
        }

        $scale = $max / $longest;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        // Preserve transparency (PNG/WebP) through the resample.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        // Re-enable blending so the watermark text composites correctly.
        imagealphablending($dst, true);

        return $dst;
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
