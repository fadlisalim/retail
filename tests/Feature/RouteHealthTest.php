<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every admin and account page against the demo dataset to catch broken
 * views, missing variables, or bad route bindings that unit tests would miss.
 */
class RouteHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_pages_render_for_super_admin(): void
    {
        $admin = User::where('email', 'superadmin@rekasurya.test')->first();
        $this->actingAs($admin);

        $indexRoutes = [
            'admin.dashboard', 'admin.products.index', 'admin.products.create',
            'admin.categories.index', 'admin.categories.create',
            'admin.brands.index', 'admin.brands.create',
            'admin.attributes.index', 'admin.attributes.create',
            'admin.prices.index', 'admin.warehouses.index', 'admin.warehouses.create',
            'admin.coupons.index', 'admin.coupons.create',
            'admin.orders.index', 'admin.quotations.index', 'admin.reviews.index',
            'admin.customers.index', 'admin.banners.index', 'admin.banners.create',
            'admin.pages.index', 'admin.pages.create', 'admin.articles.index', 'admin.articles.create',
            'admin.faqs.index', 'admin.faqs.create', 'admin.settings.edit',
            'admin.users.index', 'admin.users.create', 'admin.audit.index',
        ];

        foreach ($indexRoutes as $name) {
            $this->get(route($name))->assertOk();
        }

        // Detail / edit pages that require a bound model.
        $this->get(route('admin.products.edit', Product::first()))->assertOk();
        $this->get(route('admin.orders.show', Order::first()))->assertOk();
        $this->get(route('admin.quotations.show', Quotation::first()))->assertOk();
        $this->get(route('admin.customers.show', User::where('is_staff', false)->first()))->assertOk();
    }

    public function test_account_pages_render_for_customer(): void
    {
        $customer = Order::whereNotNull('user_id')->first()->user;
        $this->actingAs($customer);

        foreach ([
            'account.dashboard', 'account.profile', 'account.orders', 'account.quotations',
            'account.wishlist', 'account.reviews', 'account.notifications',
            'account.addresses.index', 'account.addresses.create',
        ] as $name) {
            $this->get(route($name))->assertOk();
        }

        $order = $customer->orders()->first();
        $this->get(route('account.orders.show', $order))->assertOk();
    }
}
