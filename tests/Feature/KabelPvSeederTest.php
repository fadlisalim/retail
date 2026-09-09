<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\KabelPvSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kabel PV per meter: harga & margin terkunci, hanya hitam, idempotent. */
class KabelPvSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Modal per meter (referensi owner) — margin dari revenue wajib ≥ 20%. */
    private const COST = [
        'kabel-pv-dc-solar-1x4mm-hitam-per-meter' => 14000,
        'kabel-pv-dc-solar-1x6mm-hitam-per-meter' => 19000,
    ];

    private const PRICE = [
        'kabel-pv-dc-solar-1x4mm-hitam-per-meter' => 18000,
        'kabel-pv-dc-solar-1x6mm-hitam-per-meter' => 24000,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Category::factory()->create(['name' => 'Kabel PV', 'slug' => 'kabel-konektor-proteksi-kabel-pv']);
        $this->seed(KabelPvSeeder::class);
    }

    public function test_both_sizes_exist_with_locked_prices_and_healthy_margin(): void
    {
        foreach (self::PRICE as $slug => $price) {
            $p = Product::where('slug', $slug)->first();
            $this->assertNotNull($p, $slug);
            $this->assertSame((float) $price, (float) $p->price);
            $this->assertSame((float) self::COST[$slug], (float) $p->cost_price);
            // Margin dari revenue ≥ 20% → harga ≥ modal ÷ 0,8.
            $this->assertGreaterThanOrEqual(self::COST[$slug] / 0.8, (float) $p->price);
            $this->assertSame('meter', $p->unit);
            $this->assertSame('published', $p->status);
        }
    }

    public function test_only_black_and_sold_per_meter_is_stated(): void
    {
        $p = Product::where('slug', 'kabel-pv-dc-solar-1x4mm-hitam-per-meter')->first();

        $this->assertStringContainsString('hanya tersedia hitam', mb_strtolower($p->specifications));
        $this->assertStringContainsString('per meter', mb_strtolower($p->short_description));
        $this->assertStringContainsString('Hitam', $p->name);
    }

    public function test_rerun_does_not_overwrite_admin_edits(): void
    {
        $p = Product::where('slug', 'kabel-pv-dc-solar-1x6mm-hitam-per-meter')->first();
        $p->forceFill(['price' => 25000])->save();

        $this->seed(KabelPvSeeder::class);

        $this->assertSame(25000.0, (float) $p->fresh()->price);
        $this->assertSame(2, Product::where('slug', 'like', 'kabel-pv-dc-solar-%')->count());
    }

    public function test_cable_lands_in_the_pv_cable_category(): void
    {
        $p = Product::where('slug', 'kabel-pv-dc-solar-1x4mm-hitam-per-meter')->first();

        $this->assertSame('kabel-konektor-proteksi-kabel-pv', $p->category?->slug);
    }
}
