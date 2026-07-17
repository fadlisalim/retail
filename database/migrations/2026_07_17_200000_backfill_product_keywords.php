<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill SEO keywords for products that don't have any yet. New products get
 * keywords automatically via Product::booted() (saving hook); this fills the
 * existing catalogue. Admin-entered keywords are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Product::query()
            ->where(fn ($q) => $q->whereNull('keywords')->orWhere('keywords', ''))
            ->with(['brand', 'category.parent'])
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    $product->keywords = $product->generateKeywords();
                    $product->saveQuietly(); // keywords-only update, skip events
                }
            });
    }

    public function down(): void
    {
        // Data backfill — nothing to reverse (keywords are content).
    }
};
