<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            // Slug is unique globally so /kategori/{slug} resolves without the full path.
            $table->string('slug')->unique();
            $table->string('path')->nullable()->index(); // materialised ancestor path e.g. 1/4/9
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active']);
        });

        // Old slugs -> permanent redirect target, so shared links never break.
        Schema::create('category_slug_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('old_slug')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_slug_histories');
        Schema::dropIfExists('categories');
    }
};
