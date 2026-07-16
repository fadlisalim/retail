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

    public function test_apply_changes_file_but_preserves_dimensions(): void
    {
        Storage::fake('public');
        $path = 'products/wm.jpg';
        $this->makeImage($path);

        $before = Storage::disk('public')->get($path);
        $ok = app(WatermarkService::class)->apply($path);

        $this->assertTrue($ok);
        $after = Storage::disk('public')->get($path);
        $this->assertNotSame($before, $after, 'File bytes should change after watermarking.');

        [$w, $h] = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame(400, $w);
        $this->assertSame(400, $h);
    }

    public function test_apply_returns_false_for_missing_file(): void
    {
        Storage::fake('public');
        $this->assertFalse(app(WatermarkService::class)->apply('products/nope.jpg'));
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
