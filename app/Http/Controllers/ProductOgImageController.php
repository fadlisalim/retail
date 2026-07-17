<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a 1200x630 landscape social-share image for a product (photo on a
 * branded canvas + name + price + logo). WhatsApp/Facebook only render the LARGE
 * image card for landscape og:images, so square (1:1) product photos alone show
 * as a small side thumbnail — this wraps them in a proper 1.91:1 card.
 *
 * The result is cached to storage/app/public/og and re-generated when the
 * product's image/price/name changes.
 */
class ProductOgImageController extends Controller
{
    private const W = 1200;
    private const H = 630;

    public function __invoke(Product $product)
    {
        $signature = md5(implode('|', [
            $product->id,
            $product->name,
            $product->main_image_path,
            $product->effectivePrice(),
            $product->price,
            optional($product->updated_at)->timestamp,
        ]));

        $rel = "og/{$product->id}-{$signature}.png";
        $abs = Storage::disk('public')->path($rel);

        if (! is_file($abs)) {
            @mkdir(dirname($abs), 0775, true);
            $this->render($product, $abs);
        }

        return response()->file($abs, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    private function fontBold(): string
    {
        return resource_path('fonts/DejaVuSans-Bold.ttf');
    }

    private function fontReg(): string
    {
        return resource_path('fonts/DejaVuSans.ttf');
    }

    private function render(Product $product, string $out): void
    {
        $im = imagecreatetruecolor(self::W, self::H);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $white);

        $teal = imagecolorallocate($im, 15, 118, 110);      // brand-600
        $dark = imagecolorallocate($im, 17, 24, 39);         // gray-900
        $gray = imagecolorallocate($im, 107, 114, 128);      // gray-500
        $orange = imagecolorallocate($im, 249, 115, 22);     // accent-500
        $panel = imagecolorallocate($im, 244, 247, 246);     // light panel

        // Left panel that holds the product photo.
        $lx = 48; $ly = 48; $lw = 544; $lh = self::H - 96;
        imagefilledrectangle($im, $lx, $ly, $lx + $lw, $ly + $lh, $panel);

        $photoPath = $product->main_image_path
            ? Storage::disk('public')->path($product->main_image_path)
            : null;
        $photo = ($photoPath && is_file($photoPath))
            ? @imagecreatefromstring((string) file_get_contents($photoPath))
            : null;

        if ($photo) {
            $pw = imagesx($photo); $ph = imagesy($photo);
            $pad = 28;
            $s = min(($lw - 2 * $pad) / $pw, ($lh - 2 * $pad) / $ph);
            $nw = (int) round($pw * $s); $nh = (int) round($ph * $s);
            $dx = $lx + (int) (($lw - $nw) / 2);
            $dy = $ly + (int) (($lh - $nh) / 2);
            imagecopyresampled($im, $photo, $dx, $dy, 0, 0, $nw, $nh, $pw, $ph);
        } else {
            // No photo: drop the logo mark in the panel centre.
            $this->placeImage($im, public_path('images/logo.png'), $lx + 172, $ly + 172, 200, 200);
        }

        // Right column.
        $rx = 640; $rw = self::W - $rx - 60;

        // Logo + wordmark
        $this->placeImage($im, public_path('images/logo.png'), $rx, 54, 44, 44);
        imagettftext($im, 22, 0, $rx + 58, 86, $dark, $this->fontBold(), 'Energi.');
        $bb = imagettfbbox(22, 0, $this->fontBold(), 'Energi.');
        imagettftext($im, 22, 0, $rx + 58 + ($bb[2] - $bb[0]) + 2, 86, $orange, $this->fontBold(), 'Click');

        // Product name (wrapped, max 3 lines)
        $lines = $this->wrap($this->fontBold(), 32, $product->name, $rw, 3);
        $y = 176;
        foreach ($lines as $line) {
            imagettftext($im, 32, 0, $rx, $y, $dark, $this->fontBold(), $line);
            $y += 46;
        }

        // Price
        $y += 34;
        if ($product->requires_quotation || $product->price_status === 'call_for_price') {
            imagettftext($im, 40, 0, $rx, $y, $teal, $this->fontBold(), 'Minta Penawaran');
        } else {
            imagettftext($im, 54, 0, $rx, $y, $teal, $this->fontBold(), rupiah($product->effectivePrice()));
            if ($product->isOnSale()) {
                $y += 44;
                $orig = rupiah($product->price);
                imagettftext($im, 22, 0, $rx, $y, $gray, $this->fontReg(), $orig);
                // strike-through
                $sb = imagettfbbox(22, 0, $this->fontReg(), $orig);
                imagefilledrectangle($im, $rx, $y - 8, $rx + ($sb[2] - $sb[0]), $y - 6, $gray);
            }
        }

        // Footer
        imagettftext($im, 22, 0, $rx, self::H - 44, $gray, $this->fontReg(), 'energi.click');

        imagepng($im, $out);
        imagedestroy($im);
        if ($photo) {
            imagedestroy($photo);
        }
    }

    /** Draw a (possibly transparent PNG) image scaled to fit within w x h. */
    private function placeImage(\GdImage $dst, string $path, int $x, int $y, int $w, int $h): void
    {
        if (! is_file($path)) {
            return;
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            return;
        }
        imagealphablending($dst, true);
        $sw = imagesx($src); $sh = imagesy($src);
        $s = min($w / $sw, $h / $sh);
        $nw = (int) round($sw * $s); $nh = (int) round($sh * $s);
        imagecopyresampled($dst, $src, $x + (int) (($w - $nw) / 2), $y + (int) (($h - $nh) / 2), 0, 0, $nw, $nh, $sw, $sh);
        imagedestroy($src);
    }

    /** Word-wrap text to a pixel width using the TTF metrics; cap at $maxLines. */
    private function wrap(string $font, int $size, string $text, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $try = $current === '' ? $word : $current.' '.$word;
            $bb = imagettfbbox($size, 0, $font, $try);
            if (($bb[2] - $bb[0]) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
                if (count($lines) === $maxLines) {
                    break;
                }
            } else {
                $current = $try;
            }
        }
        if (count($lines) < $maxLines && $current !== '') {
            $lines[] = $current;
        }
        // Ellipsis if truncated
        if (count($lines) === $maxLines) {
            $consumed = implode(' ', $lines);
            if (mb_strlen($consumed) < mb_strlen(trim($text))) {
                $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1]).'…';
            }
        }

        return $lines;
    }
}
