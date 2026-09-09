<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Konfirmasi email penarikan dana afiliator (anti pembajakan akun).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_payouts', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_payouts', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
