<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // Width within a grid row: full | half | third (1, 2, or 3 per row).
            $table->string('span', 10)->default('third')->after('position');
            // Portrait (9:16) vs landscape (16:9) for video-position banners.
            $table->boolean('is_portrait')->default(false)->after('span');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['span', 'is_portrait']);
        });
    }
};
