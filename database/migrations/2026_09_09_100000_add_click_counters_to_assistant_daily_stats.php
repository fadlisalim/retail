<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Funnel Kirana: klik kartu produk & klik tombol WhatsApp per hari.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_daily_stats', function (Blueprint $table) {
            $table->unsignedInteger('product_clicks')->default(0)->after('sessions');
            $table->unsignedInteger('wa_clicks')->default(0)->after('product_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_daily_stats', function (Blueprint $table) {
            $table->dropColumn(['product_clicks', 'wa_clicks']);
        });
    }
};
