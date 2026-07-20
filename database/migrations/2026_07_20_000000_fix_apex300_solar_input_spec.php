<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Corrects the BLUETTI Apex 300 solar-input figures that were seeded from a
 * reseller listing (2400W / 19,2kW) to BLUETTI's official spec: 1200W native
 * (12–150V), up to 4000W with SolarX 4K. Surgical string replace so any other
 * admin edits to the product are preserved; idempotent (no-op if already fixed).
 */
return new class extends Migration
{
    private array $replacements = [
        'hingga 2400W solar langsung, dan hingga 19,2kW dengan SolarX 4K.'
            => 'hingga 1200W solar langsung (12–150V), dan hingga 4000W dengan SolarX 4K.',
        'Pengisian surya besar</strong> — hingga 1200W'
            => 'Pengisian surya</strong> — hingga 1200W',
        'Hingga 2400W (OCV 12–60V, MC4); hingga 19,2kW dengan SolarX 4K'
            => 'Hingga 1200W (12–150V, MC4); hingga 4000W dengan SolarX 4K',
    ];

    public function up(): void
    {
        $product = Product::where('slug', 'bluetti-apex-300')->first();
        if (! $product) {
            return;
        }

        foreach (['description', 'specifications'] as $field) {
            $value = (string) $product->{$field};
            foreach ($this->replacements as $from => $to) {
                $value = str_replace($from, $to, $value);
            }
            $product->{$field} = $value;
        }
        $product->saveQuietly();
    }

    public function down(): void
    {
        // One-way data correction — no rollback.
    }
};
