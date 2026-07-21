<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category-page banner slider: banners with position "category" can target a
 * specific category (or apply to all category pages when NULL) — mirrors the
 * existing brand-page slider (brand_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('brand_id')
                ->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
