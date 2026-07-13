<?php

namespace App\Services\Shipping;

/**
 * Billable-weight maths (section 16). Pure and side-effect free so it is trivially
 * unit-testable:
 *
 *   berat_volumetrik = panjang × lebar × tinggi ÷ divisor   (cm -> kg)
 *   berat_ditagihkan = max(berat_aktual, berat_volumetrik)  rounded up
 *
 * The divisor is NEVER hardcoded — it is supplied per courier/service.
 */
class WeightCalculator
{
    /** Volumetric weight in grams for a single box, given cm dimensions + divisor. */
    public function volumetricGrams(float $lengthCm, float $widthCm, float $heightCm, int $divisor): int
    {
        if ($divisor <= 0) {
            return 0;
        }

        $volumeCm3 = $lengthCm * $widthCm * $heightCm;
        $kg = $volumeCm3 / $divisor; // divisor already expressed so cm³/divisor => kg

        return (int) round($kg * 1000);
    }

    /**
     * Billable weight in grams: the greater of total actual and total volumetric
     * weight, rounded UP to the nearest rounding step (default 1 kg).
     *
     * @param  int  $actualGrams  summed real weight of all items
     * @param  float  $totalVolumeCm3  summed L×W×H×qty of all items
     */
    public function billableGrams(int $actualGrams, float $totalVolumeCm3, int $divisor, int $roundingGrams = 1000): int
    {
        $volumetric = $divisor > 0 ? (int) round(($totalVolumeCm3 / $divisor) * 1000) : 0;
        $billable = max($actualGrams, $volumetric);

        if ($roundingGrams > 1) {
            $billable = (int) (ceil($billable / $roundingGrams) * $roundingGrams);
        }

        return max($billable, 0);
    }

    /** Convenience: convert grams to kilograms, rounded up to whole kg. */
    public function toBillableKg(int $grams): int
    {
        return (int) ceil($grams / 1000);
    }
}
