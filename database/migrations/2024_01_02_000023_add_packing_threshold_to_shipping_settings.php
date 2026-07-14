<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            // Only items whose per-unit weight is at least this many grams incur the
            // wooden-crate packing fee. 0 = charge packing on everything.
            $table->unsignedInteger('packing_min_item_grams')->default(5000)->after('packing_fee');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_settings', function (Blueprint $table) {
            $table->dropColumn('packing_min_item_grams');
        });
    }
};
