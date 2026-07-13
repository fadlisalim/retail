<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_coupon_respects_max_discount(): void
    {
        Coupon::create([
            'code' => 'DISC20', 'type' => 'percent', 'value' => 20,
            'min_subtotal' => 0, 'max_discount' => 100000, 'is_active' => true,
        ]);

        $result = app(CouponService::class)->evaluate('DISC20', 1000000);
        // 20% of 1,000,000 = 200,000 but capped at 100,000.
        $this->assertEquals(100000, $result['discount']);
        $this->assertNull($result['error']);
    }

    public function test_coupon_below_minimum_subtotal_is_rejected(): void
    {
        Coupon::create(['code' => 'MIN500', 'type' => 'fixed', 'value' => 50000, 'min_subtotal' => 500000, 'is_active' => true]);

        $result = app(CouponService::class)->evaluate('MIN500', 100000);
        $this->assertEquals(0, $result['discount']);
        $this->assertNotNull($result['error']);
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        Coupon::create(['code' => 'OFF', 'type' => 'fixed', 'value' => 10000, 'is_active' => false]);

        $result = app(CouponService::class)->evaluate('OFF', 100000);
        $this->assertNull($result['coupon']);
    }

    public function test_free_shipping_coupon_flags_shipping(): void
    {
        Coupon::create(['code' => 'FREEONG', 'type' => 'free_shipping', 'value' => 0, 'min_subtotal' => 0, 'is_active' => true]);

        $result = app(CouponService::class)->evaluate('FREEONG', 100000);
        $this->assertTrue($result['free_shipping']);
    }
}
