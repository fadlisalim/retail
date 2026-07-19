<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductVideoTest extends TestCase
{
    use RefreshDatabase;

    private function catalogAdmin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());

        return $user;
    }

    public function test_admin_can_upload_a_short_video_into_the_gallery(): void
    {
        Storage::fake('public');
        $admin = $this->catalogAdmin();
        $product = Product::factory()->create(['main_image_path' => 'products/main.webp']);

        $this->actingAs($admin)->post(route('admin.products.videofile.store', $product), [
            'video' => UploadedFile::fake()->create('clip.mp4', 900, 'video/mp4'),
            'poster' => UploadedFile::fake()->image('poster.jpg', 400, 400),
        ])->assertRedirect();

        $row = $product->images()->whereNotNull('video_path')->first();
        $this->assertNotNull($row, 'A video gallery row should be created.');
        $this->assertTrue($row->isVideo());
        Storage::disk('public')->assertExists($row->video_path);

        // The video must NOT become the catalogue thumbnail.
        $this->assertSame('products/main.webp', $product->fresh()->main_image_path);
    }

    public function test_video_row_cannot_be_set_as_main_image(): void
    {
        Storage::fake('public');
        $admin = $this->catalogAdmin();
        $product = Product::factory()->create(['main_image_path' => 'products/main.webp']);
        $video = $product->images()->create([
            'path' => 'products/poster.webp',
            'video_path' => 'products/videos/clip.mp4',
            'alt' => 'v',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.products.image.primary', [$product, $video]))
            ->assertRedirect();

        // Unchanged — a video can never be the thumbnail.
        $this->assertSame('products/main.webp', $product->fresh()->main_image_path);
    }

    public function test_oversize_video_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->catalogAdmin();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.videofile.store', $product), [
            'video' => UploadedFile::fake()->create('big.mp4', 25000, 'video/mp4'), // ~25 MB > 20 MB
        ])->assertSessionHasErrors('video');

        $this->assertSame(0, $product->images()->whereNotNull('video_path')->count());
    }
}
