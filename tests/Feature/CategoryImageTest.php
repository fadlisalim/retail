<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryImageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());

        return $user;
    }

    public function test_main_category_stores_uploaded_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());

        $this->post(route('admin.categories.store'), [
            'name' => 'Panel Surya',
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('panel.jpg', 200, 200),
        ])->assertRedirect();

        $category = Category::where('slug', 'panel-surya')->first();
        $this->assertNotNull($category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
    }

    public function test_sub_category_ignores_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $parent = Category::create(['name' => 'Panel Surya', 'slug' => 'panel-surya', 'is_active' => true]);

        $this->post(route('admin.categories.store'), [
            'parent_id' => $parent->id,
            'name' => 'Monocrystalline',
            'is_active' => '1',
            'image' => UploadedFile::fake()->image('mono.jpg', 200, 200),
        ])->assertRedirect();

        $child = Category::where('slug', 'monocrystalline')->first();
        $this->assertNull($child->image_path);
    }

    public function test_remove_image_deletes_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $path = UploadedFile::fake()->image('old.jpg')->store('categories', 'public');
        $category = Category::create(['name' => 'Inverter', 'slug' => 'inverter', 'is_active' => true, 'image_path' => $path]);

        $this->put(route('admin.categories.update', $category), [
            'name' => 'Inverter',
            'slug' => 'inverter',
            'is_active' => '1',
            'remove_image' => '1',
        ])->assertRedirect();

        $this->assertNull($category->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }
}
