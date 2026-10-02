<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\WatermarkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Gambar promosi 1:1 (Okt 2026, 19 gambar) untuk produk PJU Tenaga Surya + pompa LARENS
 * menggantikan foto hasil potongan brosur sebagai foto utama. Gambar sudah
 * berlogo Energi.click, jadi hanya dioptimalkan (webp) tanpa watermark bertumpuk.
 *
 * Idempotent: produk yang sudah memakai gambar promo dilewati; foto brosur yang
 * gelap/kurang informatif dibuang dari galeri, halaman brosur yang informatif
 * tetap sebagai foto kedua. Produk yang belum ada (belum di-seed) dilewati.
 */
class PjuGambarPromosiSeeder extends Seeder
{
    private const ASSETS = __DIR__.'/assets/pju';

    /**
     * SKU produk => [file promo, SKU varian yang memakai gambar itu, foto brosur lama yang dibuang].
     *
     * @var array<string, array{promo: array<string, list<string>>, drop?: list<string>}>
     */
    private const MAP = [
        'ICOM-IC-AIOM' => ['promo' => ['promo-ic-aiom60.jpg' => ['ICOM-IC-AIOM60'], 'promo-ic-aiom80.jpg' => ['ICOM-IC-AIOM80'], 'promo-ic-aiom100.jpg' => ['ICOM-IC-AIOM100']]],
        'ICOM-IC-TEEN' => ['promo' => ['promo-ic-teen90.jpg' => ['ICOM-IC-TEEN90']]], // varian 120/150/180 tetap foto brosur masing-masing
        'ICOM-IC-FIN' => ['promo' => ['promo-ic-fin100.jpg' => ['ICOM-IC-FIN100'], 'promo-ic-fin120.jpg' => ['ICOM-IC-FIN120']]],
        'ICOM-IC-YIN' => ['promo' => ['promo-ic-yin40.jpg' => ['ICOM-IC-YIN40'], 'promo-ic-yin60.jpg' => ['ICOM-IC-YIN60'], 'promo-ic-yin80.jpg' => ['ICOM-IC-YIN80']]],
        'SOLARI-SL-MW' => ['promo' => ['promo-sl-mw80.jpg' => ['SOLARI-SL-MW80'], 'promo-sl-mw100.jpg' => ['SOLARI-SL-MW100'], 'promo-sl-mw120.jpg' => ['SOLARI-SL-MW120']]],
        'LEIND-LI-VILL100' => ['promo' => ['promo-li-vill100.jpg' => []]],
        'SUNYO-SY-BEK' => ['promo' => ['promo-sy-bek.jpg' => ['SUNYO-SY-BEK60', 'SUNYO-SY-BEK90', 'SUNYO-SY-BEK110']]],
        'LEIND-LI-SLIM100' => ['promo' => ['promo-li-slim.jpg' => []], 'drop' => ['li-slim']],
        'LEIND-LI-CITY' => ['promo' => ['promo-li-city.jpg' => ['LEIND-LI-CITY50-100', 'LEIND-LI-CITY100-135', 'LEIND-LI-CITY150-160', 'LEIND-LI-CITY150-12V-200']]],
        'LEIND-LI-RON' => ['promo' => ['promo-li-ron.jpg' => ['LEIND-LI-RON85', 'LEIND-LI-RON110', 'LEIND-LI-RON128']], 'drop' => ['li-ron']],
        'LEIND-LI-ZLW' => ['promo' => ['promo-li-zlw.jpg' => ['LEIND-LI-ZLW100', 'LEIND-LI-ZLW180', 'LEIND-LI-ZLW240']], 'drop' => ['li-zlw']],
        'LARENS-PSS-HYBRID' => ['promo' => ['promo-larens-pompa.jpg' => []], 'drop' => ['larens-4pss-tabel']],
    ];

    private int $updated = 0;

    private int $skipped = 0;

    public function run(): void
    {
        $watermark = app(WatermarkService::class);

        foreach (self::MAP as $sku => $cfg) {
            $product = Product::where('sku', $sku)->first();
            if (! $product) {
                continue;
            }

            $first = null;
            foreach ($cfg['promo'] as $file => $variantSkus) {
                $stored = $this->store($file, $watermark);
                if (! $stored) {
                    continue;
                }
                $first ??= $stored;

                $exists = $product->images()->where('path', $stored)->exists();
                if (! $exists) {
                    $product->images()->create([
                        'path' => $stored, 'alt' => $product->name,
                        'sort_order' => 0, 'watermarked_at' => now(), // sudah berlogo
                    ]);
                }
                if ($variantSkus) {
                    ProductVariant::where('product_id', $product->id)->whereIn('sku', $variantSkus)->update(['image_path' => $stored]);
                }
            }

            if (! $first) {
                continue;
            }

            if ($product->main_image_path === $first) {
                $this->skipped++;
            } else {
                $product->forceFill(['main_image_path' => $first])->save();
                $this->updated++;
            }

            foreach ($cfg['drop'] ?? [] as $stem) {
                ProductImage::where('product_id', $product->id)->whereNull('video_path')
                    ->where('path', 'like', 'products/pju/'.$stem.'.%')->delete();
            }

            // Promo di depan, lalu sisa galeri urut lama.
            $order = 0;
            foreach ($product->images()->orderByRaw("CASE WHEN path LIKE 'products/pju/promo-%' THEN 0 ELSE 1 END")->orderBy('sort_order')->orderBy('id')->get() as $image) {
                $image->forceFill(['sort_order' => $order++])->save();
            }
        }

        $this->command?->info("Gambar promosi PJU: {$this->updated} produk diperbarui, {$this->skipped} sudah memakai gambar promo.");
    }

    /** Salin ke disk public (sekali saja) dan optimalkan ke webp tanpa watermark; kembalikan path akhir. */
    private function store(string $file, WatermarkService $watermark): ?string
    {
        $src = self::ASSETS.'/'.$file;
        if (! is_file($src)) {
            return null;
        }
        $stem = 'products/pju/'.pathinfo($file, PATHINFO_FILENAME);
        $disk = Storage::disk('public');
        if ($disk->exists("{$stem}.webp")) {
            return "{$stem}.webp";
        }
        if (! $disk->exists("{$stem}.jpg")) {
            $disk->put("{$stem}.jpg", (string) file_get_contents($src));
        }

        return $watermark->isSupported() ? ($watermark->optimize("{$stem}.jpg") ?? "{$stem}.jpg") : "{$stem}.jpg";
    }
}
