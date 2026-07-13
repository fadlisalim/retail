<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucwords($this->faker->unique()->words(3, true));
        $price = $this->faker->numberBetween(100000, 20000000);

        return [
            'sku' => 'SKU-'.strtoupper(Str::random(8)),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'product_type' => 'simple',
            'condition' => 'new',
            'short_description' => $this->faker->sentence(),
            'description' => '<p>'.$this->faker->paragraph().'</p>',
            'price' => $price,
            'sale_price' => null,
            'cost_price' => round($price * 0.8),
            'price_includes_tax' => false,
            'is_taxable' => true,
            'stock' => 100,
            'min_stock' => 5,
            'unit' => 'unit',
            'weight_grams' => 5000,
            'length_cm' => 30,
            'width_cm' => 30,
            'height_cm' => 20,
            'status' => 'published',
            'is_purchasable' => true,
            'requires_quotation' => false,
            'min_purchase' => 1,
            'published_at' => now(),
        ];
    }

    public function onSale(float $salePrice): static
    {
        return $this->state(['sale_price' => $salePrice, 'is_promo' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function quotationOnly(): static
    {
        return $this->state(['requires_quotation' => true, 'is_purchasable' => false]);
    }
}
