<?php

namespace Tests\Feature;

use App\Models\Brand;
use Database\Seeders\BluettiAffiliateRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Penyeragaman fee afiliator Bluetti ke 5%. */
class BluettiAffiliateRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_bluetti_products_get_a_five_percent_rate_and_others_are_untouched(): void
    {
        $bluetti = Brand::create(['name' => 'BLUETTI', 'slug' => 'bluetti', 'is_active' => true]);
        $other = Brand::create(['name' => 'JSD Solar', 'slug' => 'jsd-solar', 'is_active' => true]);

        $default = $this->stockedProduct(0, ['brand_id' => $bluetti->id, 'affiliate_rate' => null]);
        $custom = $this->stockedProduct(0, ['brand_id' => $bluetti->id, 'affiliate_rate' => 7.5]);
        $foreign = $this->stockedProduct(0, ['brand_id' => $other->id, 'affiliate_rate' => 3]);

        $this->seed(BluettiAffiliateRateSeeder::class);

        $this->assertEquals(5, (float) $default->fresh()->affiliate_rate);
        $this->assertEquals(5, (float) $custom->fresh()->affiliate_rate, 'Penyeragaman menimpa rate lama Bluetti.');
        $this->assertEquals(3, (float) $foreign->fresh()->affiliate_rate, 'Brand lain tidak boleh tersentuh.');
    }

    public function test_it_is_a_no_op_when_the_brand_does_not_exist(): void
    {
        $product = $this->stockedProduct(0, ['affiliate_rate' => 2]);

        $this->seed(BluettiAffiliateRateSeeder::class);

        $this->assertEquals(2, (float) $product->fresh()->affiliate_rate);
    }
}
