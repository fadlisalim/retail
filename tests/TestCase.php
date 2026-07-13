<?php

namespace Tests;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Ensure a default warehouse exists for stock operations. */
    protected function defaultWarehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['code' => 'WH-TEST'],
            ['name' => 'Gudang Test', 'is_default' => true, 'is_active' => true],
        );
    }

    /** Create a published product and load real stock through the ledger. */
    protected function stockedProduct(int $stock = 100, array $overrides = []): Product
    {
        $this->defaultWarehouse();
        $product = Product::factory()->create($overrides);

        if ($stock > 0) {
            app(StockService::class)->adjust(
                $product, null, $stock, \App\Enums\StockMovementType::Purchase,
            );
            $product->refresh();
        }

        return $product;
    }

    protected function customer(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['is_staff' => false, 'is_active' => true], $overrides));
    }
}
