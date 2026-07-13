<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_customer_without_role_is_forbidden(): void
    {
        $this->actingAs($this->customer());
        $this->get('/admin')->assertForbidden();
    }

    public function test_catalog_admin_can_manage_products_but_not_users(): void
    {
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());

        $this->actingAs($user);
        $this->get('/admin/produk')->assertOk();          // has catalog.manage
        $this->get('/admin/user')->assertForbidden();      // lacks user.manage
    }

    public function test_super_admin_can_access_everything(): void
    {
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'super-admin')->first());

        $this->actingAs($user);
        $this->get('/admin')->assertOk();
        $this->get('/admin/user')->assertOk();
        $this->get('/admin/pengaturan')->assertOk();
    }
}
