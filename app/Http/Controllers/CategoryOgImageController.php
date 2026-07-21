<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a 1200x630 landscape social-share image for a category page
 * (category image on a branded canvas + name + product count + "mulai dari"
 * price). Mirrors ProductOgImageController so WhatsApp/Facebook show the large
 * landscape card instead of the generic site logo. Cached under
 * storage/app/public/og and re-generated when the category changes.
 */
class CategoryOgImageController extends Controller
{
    private const W = 1200;
    private const H = 630;

    public function __invoke(Category $category)
    {
        abort_unless($category->is_active, 404);

        [$count, $minPrice] = $this->stats($category);

        $signature = md5(implode('|', [
            $category->id,
            $category->name,
            (string) $category->image_path,
            (string) $category->banner_path,
            $count,
            (string) $minPrice,
            optional($category->updated_at)->timestamp,
        ]));

        $rel = "og/cat-{$category->id}-{$signature}.png";
        $abs = Storage::disk('public')->path($rel);

        if (! is_file($abs)) {
            @mkdir(dirname($abs), 0775, true);
            $this->render($category, $count, $minPrice, $abs);
        }

        return response()->file($abs, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /** Published product count + cheapest effective price in the subtree. */
    private function stats(Category $category): array
    {
        $ids = $category->descendantIds();
        $query = Product::published()->where(function ($q) use ($ids) {
            $q->whereIn('category_id', $ids)
                ->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $ids));
        });

        $count = (int) $query->clone()->count();
        $min = $query->clone()->selectRaw('MIN(COALESCE(sale_price, price)) as m')->value('m');

        return [$count, $min !== null ? (float) $min : null];
    }

    private function fontBold(): string
    {
        return resource_path('fonts/DejaVuSans-Bold.ttf');
    }

    private function fontReg(): string
    {
        return resource_path('fonts/DejaVuSans.ttf');
    }

    private function render(Category $category, int $count, ?float $minPrice, string $out): void
    {
        $im = imagecreatetruecolor(self::W, self::H);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $white);

        $teal = imagecolorallocate($im, 15, 118, 110);      // brand-600
        $dark = imagecolorallocate($im, 17, 24, 39);         // gray-900
        $gray = imagecolorallocate($im, 107, 114, 128);      // gray-500
        $orange = imagecolorallocate($im, 249, 115, 22);     // accent-500
        $panel = imagecolorallocate($im, 244, 247, 246);     // light panel

        // Left panel with the category image (falls back to the logo mark).
        $lx = 48; $ly = 48; $lw = 544; $lh = self::H - 96;
        imagefilledrectangle($im, $lx, $ly, $lx + $lw, $ly + $lh, $panel);

        $imagePath = collect([$category->image_path, $category->banner_path])
            ->filter()
            ->map(fn ($p) => Storage::disk('public')->path($p))
            ->first(fn ($p) => is_file($p));

        $photo = $imagePath ? @imagecreatefromstring((string) file_get_contents($imagePath)) : null;

        if ($photo) {
            $pw = imagesx($photo); $ph = imagesy($photo);
            $pad = 28;
            $s = min(($lw - 2 * $pad) / $pw, ($lh - 2 * $pad) / $ph);
            $nw = (int) round($pw * $s); $nh = (int) round($ph * $s);
            imagecopyresampled($im, $photo, $lx + (int) (($lw - $nw) / 2), $ly + (int) (($lh - $nh) / 2), 0, 0, $nw, $nh, $pw, $ph);
            imagedestroy($photo);
        } else {
            $this->placeImage($im, public_path('images/logo.png'), $lx + 172, $ly + 172, 200, 200);
        }

        // Right column.
        $rx = 640; $rw = self::W - $rx - 60;

        // Logo + wordmark
        $this->placeImage($im, public_path('images/logo.png'), $rx, 54, 44, 44);
        imagettftext($im, 22, 0, $rx + 58, 86, $dark, $this->fontBold(), 'Energi.');
        $bb = imagettfbbox(22, 0, $this->fontBold(), 'Energi.');
        imagettftext($im, 22, 0, $rx + 58 + ($bb[2] - $bb[0]) + 2, 86, $orange, $this->fontBold(), 'Click');

        // "Kategori" eyebrow + name (wrapped, max 3 lines)
        imagettftext($im, 20, 0, $rx, 148, $gray, $this->fontReg(), 'Kategori');
        $y = 196;
        foreach ($this->wrap($this->fontBold(), 38, $category->name, $rw, 3) as $line) {
            imagettftext($im, 38, 0, $rx, $y, $dark, $this->fontBold(), $line);
            $y += 54;
        }

        // Product count + starting price.
        $y += 28;
        if ($count > 0) {
            imagettftext($im, 24, 0, $rx, $y, $gray, $this->fontReg(), $count.' produk tersedia');
            if ($minPrice !== null) {
                $y += 56;
                imagettftext($im, 24, 0, $rx, $y - 34, $gray, $this->fontReg(), 'Mulai dari');
                imagettftext($im, 42, 0, $rx, $y + 14, $teal, $this->fontBold(), rupiah($minPrice));
            }
        } else {
            imagettftext($im, 24, 0, $rx, $y, $gray, $this->fontReg(), 'Segera hadir');
        }

        // Footer
        imagettftext($im, 22, 0, $rx, self::H - 44, $gray, $this->fontReg(), 'energi.click');

        imagepng($im, $out);
        imagedestroy($im);
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

        return $lines;
    }
}
