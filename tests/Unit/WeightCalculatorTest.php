<?php

namespace Tests\Unit;

use App\Services\Shipping\WeightCalculator;
use PHPUnit\Framework\TestCase;

class WeightCalculatorTest extends TestCase
{
    private WeightCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new WeightCalculator();
    }

    public function test_volumetric_weight_uses_divisor(): void
    {
        // 60 x 40 x 20 cm = 48000 cm³, /6000 = 8 kg = 8000 g
        $this->assertSame(8000, $this->calc->volumetricGrams(60, 40, 20, 6000));

        // A different (per-courier) divisor yields a different volumetric weight.
        $this->assertSame(12000, $this->calc->volumetricGrams(60, 40, 20, 4000));
    }

    public function test_billable_weight_is_the_greater_of_actual_and_volumetric(): void
    {
        // Actual 3 kg but volumetric 8 kg -> billable 8 kg.
        $volume = 60 * 40 * 20; // 48000 cm³ -> 8 kg at divisor 6000
        $this->assertSame(8000, $this->calc->billableGrams(3000, $volume, 6000, 1));

        // Actual 10 kg but volumetric only 8 kg -> billable 10 kg.
        $this->assertSame(10000, $this->calc->billableGrams(10000, $volume, 6000, 1));
    }

    public function test_billable_weight_rounds_up_to_the_step(): void
    {
        // 3200 g actual, negligible volume, rounded up to the nearest 1000 g -> 4000 g.
        $this->assertSame(4000, $this->calc->billableGrams(3200, 0, 6000, 1000));
    }

    public function test_zero_divisor_is_safe(): void
    {
        $this->assertSame(0, $this->calc->volumetricGrams(10, 10, 10, 0));
    }

    public function test_billable_kg_rounds_up(): void
    {
        $this->assertSame(4, $this->calc->toBillableKg(3200));
        $this->assertSame(3, $this->calc->toBillableKg(3000));
    }
}
