<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Ads: simpan Google Click ID (gclid) per kunjungan dan kaitkan pesanan /
 * permintaan penawaran ke kunjungannya, supaya pesanan yang LUNAS bisa diimpor
 * balik ke Google Ads sebagai konversi offline (optimasi ke pembeli nyata).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table) {
            $table->string('gclid', 120)->nullable()->after('content')->index();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('site_visit_id')->nullable()->after('affiliate_attributed_by')->constrained('site_visits')->nullOnDelete();
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('site_visit_id')->nullable()->after('user_id')->constrained('site_visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_visit_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_visit_id');
        });
        Schema::table('site_visits', function (Blueprint $table) {
            $table->dropIndex(['gclid']);
            $table->dropColumn('gclid');
        });
    }
};
