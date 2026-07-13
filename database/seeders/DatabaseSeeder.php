<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,       // roles + permissions (RBAC)
            SettingSeeder::class,    // runtime settings
            WarehouseSeeder::class,  // warehouses
            ShippingSeeder::class,   // providers, services, zones, rates
            BrandSeeder::class,      // 8 brands
            CategorySeeder::class,   // category tree
            AttributeSeeder::class,  // dynamic attribute groups
            ProductSeeder::class,    // 45+ products, variants, bundles, surplus, stock ledger
            CouponSeeder::class,     // vouchers
            UserSeeder::class,       // staff (roles) + customers (profiles, addresses)
            OrderSeeder::class,      // 10 orders, varied statuses, invoices
            ReviewSeeder::class,     // 20 reviews (verified + general)
            QuotationSeeder::class,  // 5 RFQ/quotations
            CmsSeeder::class,        // banners, pages, articles, faqs
        ]);
    }
}
