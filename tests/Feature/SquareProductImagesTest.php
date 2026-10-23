<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\WatermarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Foto produk 1:1: foto lama dirapikan lewat perintah, upload baru otomatis persegi. */
class SquareProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private function putImage(string $path, int $w, int $h): void
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 10, 10, $w - 10, $h - 10, imagecolorallocate($img, 200, 30, 30));
        ob_start();
        imagejpeg($img);
        Storage::disk('public')->put($path, (string) ob_get_clean());
    }

    private function dims(string $path): array
    {
        return array_slice(getimagesize(Storage::disk('public')->path($path)), 0, 2);
    }

    public function test_command_squares_gallery_main_and_variant_images_and_updates_references(): void
    {
        if (! app(WatermarkService::class)->isSupported()) {
            $this->markTestSkipped('GD/FreeType tidak tersedia');
        }
        Storage::fake('public');
        $this->putImage('products/tinggi.jpg', 300, 500);
        $this->putImage('products/kotak.jpg', 400, 400);
        $this->putImage('products/lebar.jpg', 600, 300);

        $product = Product::factory()->create(['main_image_path' => 'products/tinggi.jpg']);
        $product->images()->create(['path' => 'products/tinggi.jpg', 'alt' => 'a', 'sort_order' => 1]);
        $product->images()->create(['path' => 'products/kotak.jpg', 'alt' => 'b', 'sort_order' => 2]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SQ-1', 'name' => 'Varian', 'price' => 100_000, 'is_active' => true, 'image_path' => 'products/lebar.jpg']);

        $this->artisan('products:square-images --dry-run')->expectsOutputToContain('2 perlu dijadikan 1:1')->assertSuccessful();
        Storage::disk('public')->assertExists('products/tinggi.jpg');

        $this->artisan('products:square-images')->expectsOutputToContain('2 dijadikan 1:1')->assertSuccessful();

        $main = $product->fresh()->main_image_path;
        $this->assertNotSame('products/tinggi.jpg', $main);
        $this->assertSame([500, 500], $this->dims($main));
        $this->assertSame($main, $product->images()->orderBy('sort_order')->value('path'));
        $this->assertSame([600, 600], $this->dims($variant->fresh()->image_path));
        $this->assertSame('products/kotak.jpg', $product->images()->where('sort_order', 2)->value('path'));
        Storage::disk('public')->assertMissing('products/tinggi.jpg');

        // Dijalankan ulang: tidak ada yang berubah lagi.
        $this->artisan('products:square-images')->expectsOutputToContain('0 dijadikan 1:1')->assertSuccessful();
    }

    public function test_watermark_apply_can_square_new_uploads(): void
    {
        $service = app(WatermarkService::class);
        if (! $service->isSupported()) {
            $this->markTestSkipped('GD/FreeType tidak tersedia');
        }
        Storage::fake('public');
        $this->putImage('products/upload.jpg', 800, 450);

        $final = $service->apply('products/upload.jpg', square: true);

        $this->assertSame([800, 800], $this->dims($final));
    }
}
