<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rasio STOP dihitung ulang sejak campaign terakhir dilanjutkan admin (bukan dari awal), supaya "Lanjutkan" tidak langsung dijeda lagi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->timestamp('stop_window_from')->nullable()->after('pause_reason');
        });
    }

    public function down(): void
    {
        Schema::table('wa_campaigns', fn (Blueprint $table) => $table->dropColumn('stop_window_from'));
    }
};
