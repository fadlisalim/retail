<?php

namespace App\Services;

/**
 * PPN (Indonesian VAT) calculations. The rate is runtime-configurable through
 * settings (tax.ppn_percent) so finance can change it without a deploy.
 */
class TaxService
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function rate(): float
    {
        return $this->settings->ppnEnabled() ? $this->settings->ppnPercent() : 0.0;
    }

    /** Tax to ADD on top of a tax-exclusive base. */
    public function taxOnExclusive(float $base): float
    {
        return round($base * $this->rate() / 100, 2);
    }

    /** Tax already EMBEDDED inside a tax-inclusive amount. */
    public function taxWithinInclusive(float $gross): float
    {
        $rate = $this->rate();
        if ($rate <= 0) {
            return 0.0;
        }

        return round($gross - ($gross / (1 + $rate / 100)), 2);
    }
}
