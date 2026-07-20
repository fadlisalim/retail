<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised UNIQUE view counter per product (distinct visitors), alongside
 * the existing total `view_count`. A visitor is keyed by user_id when logged in,
 * else the guest session token, else IP — the same precedence used when a view
 * is recorded. Kept as a counter column so the admin list can show/sort it
 * without an aggregate query per row. Backfilled from existing product_views.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('unique_views')->default(0)->after('view_count');
        });

        // Backfill: distinct visitors per product = distinct logged-in users
        // + distinct guest tokens (no user) + distinct IPs (no user/token).
        // Portable (no DB-specific string concat).
        foreach (DB::table('products')->pluck('id') as $id) {
            $base = fn () => DB::table('product_views')->where('product_id', $id);

            $unique = $base()->whereNotNull('user_id')->distinct()->count('user_id')
                + $base()->whereNull('user_id')->whereNotNull('session_token')->distinct()->count('session_token')
                + $base()->whereNull('user_id')->whereNull('session_token')->whereNotNull('ip_address')->distinct()->count('ip_address');

            if ($unique > 0) {
                DB::table('products')->where('id', $id)->update(['unique_views' => $unique]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unique_views');
        });
    }
};
