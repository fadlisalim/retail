<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Rebrand the price-guarantee tag again: "Paling Murah!" -> "Termurah!". */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('badge_text', 'Paling Murah!')
            ->update(['badge_text' => 'Termurah!']);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('badge_text', 'Termurah!')
            ->update(['badge_text' => 'Paling Murah!']);
    }
};
