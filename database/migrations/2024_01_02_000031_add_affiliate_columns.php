<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-product commission rate (percent). Null => fall back to the global default.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('affiliate_rate', 5, 2)->nullable()->after('cost_price');
        });

        // Which affiliate an order is attributed to (last-click within the window).
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['affiliate_id']);
            $table->dropColumn('affiliate_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('affiliate_rate');
        });
    }
};
