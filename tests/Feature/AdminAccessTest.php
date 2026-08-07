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

    /**
     * Kuitansi is proof of settlement, so only finance (payment.manage) may
     * print it — sales stops at the invoice.
     */
    public function test_sales_prints_invoice_but_only_finance_prints_the_receipt(): void
    {
        $order = \App\Models\Order::create([
            'order_number' => 'ORD-KWT01',
            'public_token' => \Illuminate\Support\Str::uuid(),
            'customer_name' => 'Ussy',
            'customer_email' => 'ussy@test.id',
            'status' => \App\Enums\OrderStatus::Processing->value,
            'payment_status' => \App\Enums\PaymentStatus::Paid->value,
            'items_subtotal' => 1_500_000,
            'tax_amount' => 0,
            'grand_total' => 1_500_000,
        ]);
        $invoice = app(\App\Services\InvoiceService::class)->createForOrder($order);

        $sales = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $sales->roles()->attach(Role::where('slug', 'admin-sales')->first());

        $this->actingAs($sales);
        $this->get(route('admin.orders.show', $order))->assertOk();
        $this->get(route('invoices.show', $invoice))->assertOk();
        $this->get(route('admin.orders.receipt', $order))->assertForbidden();

        $finance = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $finance->roles()->attach(Role::where('slug', 'admin-keuangan')->first());

        $this->actingAs($finance);
        $this->get(route('invoices.show', $invoice))->assertOk();
        $this->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('Terbilang');
    }

    public function test_catalog_admin_can_upload_and_remove_brand_logo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = User::factory()->create(['is_staff' => true, 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', 'admin-katalog')->first());
        $this->actingAs($user);

        // Create with a logo.
        $this->post(route('admin.brands.store'), [
            'name' => 'Bluetti',
            'is_active' => '1',
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('bluetti.png', 200, 80),
        ])->assertRedirect(route('admin.brands.index'));

        $brand = \App\Models\Brand::where('slug', 'bluetti')->firstOrFail();
        $this->assertNotNull($brand->logo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($brand->logo_path);
        $old = $brand->logo_path;

        // Replace the logo → old file removed, new one stored.
        $this->put(route('admin.brands.update', $brand), [
            'name' => 'Bluetti',
            'slug' => 'bluetti',
            'is_active' => '1',
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('bluetti-new.png', 200, 80),
        ])->assertRedirect(route('admin.brands.index'));

        $brand->refresh();
        $this->assertNotSame($old, $brand->logo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($old);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($brand->logo_path);

        // Remove the logo.
        $current = $brand->logo_path;
        $this->put(route('admin.brands.update', $brand), [
            'name' => 'Bluetti',
            'slug' => 'bluetti',
            'is_active' => '1',
            'remove_logo' => '1',
        ])->assertRedirect(route('admin.brands.index'));

        $brand->refresh();
        $this->assertNull($brand->logo_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($current);
    }
}
