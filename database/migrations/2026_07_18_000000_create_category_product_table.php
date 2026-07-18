<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a product belong to more than one category (many-to-many), on top of its
 * primary `products.category_id` (which still drives the breadcrumb + URL).
 * Existing single-category assignments are backfilled so nothing changes for
 * products that were saved before this feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'product_id']);
        });

        // Backfill: every product's existing primary category becomes a pivot row.
        DB::statement('INSERT INTO category_product (category_id, product_id)
            SELECT category_id, id FROM products WHERE category_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
