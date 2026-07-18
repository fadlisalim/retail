<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\WatermarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WatermarkTest extends TestCase
{
    use RefreshDatabase;

    private function makeImage(string $path, int $w = 400, int $h = 400): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 220, 225, 230));
        Storage::disk('public')->put($path, ''); // ensure directory exists
        imagejpeg($im, Storage::disk('public')->path($path), 92);
        imagedestroy($im);
    }

    public function test_apply_optimises_file_and_preserves_dimensions(): void
    {
        Storage::fake('public');
        $path = 'products/wm.jpg';
        $this->makeImage($path);

        $result = app(WatermarkService::class)->apply($path);

        // Returns the final (possibly re-encoded) storage path.
        $this->assertNotNull($result);
        $this->assertTrue(Storage::disk('public')->exists($result));

        [$w, $h] = getimagesize(Storage::disk('public')->path($result));
        $this->assertSame(400, $w);
        $this->assertSame(400, $h);
    }

    public function test_apply_downscales_oversized_images(): void
    {
        Storage::fake('public');
        $path = 'products/big.jpg';
        $this->makeImage($path, 3000, 2000);

        $result = app(WatermarkService::class)->apply($path);
        $this->assertNotNull($result);

        [$w, $h] = getimagesize(Storage::disk('public')->path($result));
        $this->assertSame(1280, $w);          // longest side clamped to the max
        $this->assertSame(853, $h);           // aspect ratio preserved
    }

    public function test_apply_does_not_upscale_small_images(): void
    {
        Storage::fake('public');
        $path = 'products/small.jpg';
        $this->makeImage($path, 500, 400);

        $result = app(WatermarkService::class)->apply($path);
        $this->assertNotNull($result);

        [$w, $h] = getimagesize(Storage::disk('public')->path($result));
        $this->assertSame(500, $w);
        $this->assertSame(400, $h);
    }

    public function test_apply_returns_null_for_missing_file(): void
    {
        Storage::fake('public');
        $this->assertNull(app(WatermarkService::class)->apply('products/nope.jpg'));
    }

    public function test_command_stamps_unmarked_images_and_is_idempotent(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $this->makeImage('products/a.jpg');
        $image = $product->images()->create(['path' => 'products/a.jpg', 'alt' => 'x', 'sort_order' => 1]);

        $this->artisan('products:watermark')->assertSuccessful();
        $this->assertNotNull($image->fresh()->watermarked_at);

        // Second run finds nothing to do (already marked).
        $this->artisan('products:watermark')
            ->expectsOutputToContain('Tidak ada gambar')
            ->assertSuccessful();
    }
}
