<?php

namespace Tests\Unit;

use App\Services\SettingService;
use App\Services\TaxService;
use PHPUnit\Framework\TestCase;

class TaxServiceTest extends TestCase
{
    private function taxServiceAt(float $rate, bool $enabled = true): TaxService
    {
        $settings = $this->createMock(SettingService::class);
        $settings->method('ppnEnabled')->willReturn($enabled);
        $settings->method('ppnPercent')->willReturn($rate);

        return new TaxService($settings);
    }

    public function test_tax_added_on_exclusive_price(): void
    {
        $tax = $this->taxServiceAt(11);
        $this->assertSame(110.0, $tax->taxOnExclusive(1000));
    }

    public function test_tax_extracted_from_inclusive_price(): void
    {
        $tax = $this->taxServiceAt(11);
        // 1110 gross at 11% -> embedded tax = 110.
        $this->assertSame(110.0, $tax->taxWithinInclusive(1110));
    }

    public function test_disabled_tax_returns_zero(): void
    {
        $tax = $this->taxServiceAt(11, enabled: false);
        $this->assertSame(0.0, $tax->taxOnExclusive(1000));
        $this->assertSame(0.0, $tax->rate());
    }
}
