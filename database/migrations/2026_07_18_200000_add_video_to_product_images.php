<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product gallery row can be a short video: when video_path is set the row is a
 * video and `path` holds its poster image (used in the thumbnail strip and as the
 * <video> poster). The main product thumbnail (products.main_image_path) always
 * stays a real photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });
    }
};
