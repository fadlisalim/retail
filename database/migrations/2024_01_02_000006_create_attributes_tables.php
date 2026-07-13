<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attribute groups (e.g. "Spesifikasi Panel Surya") assigned to categories.
        Schema::create('attribute_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('unit', 30)->nullable(); // Wp, V, A, kWh...
            $table->string('type', 20)->default('text'); // text|number|select|boolean
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_comparable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_filterable');
        });

        // Predefined values for select-type attributes (used to build filter facets).
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Which attribute groups apply to which category.
        Schema::create('attribute_group_category', function (Blueprint $table) {
            $table->foreignId('attribute_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['attribute_group_id', 'category_id'], 'attr_group_category_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_group_category');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_groups');
    }
};
