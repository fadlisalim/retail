<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rebrand the price-guarantee tag: "JAMINAN HARGA TERMURAH" -> "Paling Murah!".
 * Data-only; scoped to the exact old value so it is safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('badge_text', 'JAMINAN HARGA TERMURAH')
            ->update(['badge_text' => 'Paling Murah!']);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('badge_text', 'Paling Murah!')
            ->update(['badge_text' => 'JAMINAN HARGA TERMURAH']);
    }
};
