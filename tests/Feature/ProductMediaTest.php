<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());

        return $user;
    }

    public function test_admin_can_upload_image_pdf_and_video(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(5);

        // Image (first upload becomes the main image)
        $this->post(route('admin.products.image.store', $product), [
            'images' => [UploadedFile::fake()->image('panel.jpg', 800, 800)],
        ])->assertRedirect();
        $product->refresh();
        $this->assertCount(1, $product->images);
        $this->assertNotNull($product->main_image_path);
        Storage::disk('public')->assertExists($product->images->first()->path);

        // PDF datasheet
        $this->post(route('admin.products.document.store', $product), [
            'title' => 'Datasheet 550Wp', 'type' => 'datasheet',
            'document' => UploadedFile::fake()->create('spec.pdf', 200, 'application/pdf'),
        ])->assertRedirect();
        $this->assertCount(1, $product->fresh()->documents);

        // YouTube video
        $this->post(route('admin.products.video.store', $product), [
            'title' => 'Instalasi', 'url' => 'https://www.youtube.com/watch?v=abc123',
        ])->assertRedirect();
        $this->assertCount(1, $product->fresh()->videos);

        // Delete each
        $this->delete(route('admin.products.image.destroy', $product->images->first()))->assertRedirect();
        $this->delete(route('admin.products.document.destroy', $product->fresh()->documents->first()))->assertRedirect();
        $this->delete(route('admin.products.video.destroy', $product->fresh()->videos->first()))->assertRedirect();
        $this->assertCount(0, $product->fresh()->images);
        $this->assertCount(0, $product->fresh()->documents);
        $this->assertCount(0, $product->fresh()->videos);
    }

    public function test_admin_can_reorder_gallery_images(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(5);

        $this->post(route('admin.products.image.store', $product), [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
        ])->assertRedirect();

        // Edit page renders the gallery manager with the reorder hint.
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Seret untuk mengubah urutan');

        $ids = $product->fresh()->images->pluck('id')->all();
        // Reverse the order.
        $this->post(route('admin.products.image.reorder', $product), ['order' => array_reverse($ids)])
            ->assertRedirect();

        $this->assertSame(array_reverse($ids), $product->fresh()->images->pluck('id')->all());
    }

    public function test_admin_can_set_primary_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(5);

        $this->post(route('admin.products.image.store', $product), [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect();

        $second = $product->fresh()->images()->orderBy('sort_order')->skip(1)->first();

        // The route binds the product by slug (its route key) — the same URL the UI builds.
        $this->post(route('admin.products.image.primary', ['produk' => $product, 'image' => $second]))
            ->assertRedirect();

        $this->assertSame($second->path, $product->fresh()->main_image_path);
    }

    public function test_rejects_non_pdf_document(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $product = $this->stockedProduct(5);

        $this->post(route('admin.products.document.store', $product), [
            'title' => 'x', 'type' => 'datasheet',
            'document' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors('document');
    }
}
