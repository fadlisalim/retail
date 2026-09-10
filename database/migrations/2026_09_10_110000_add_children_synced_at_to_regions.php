<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel regions diisi dari RajaOngkir/Komerce (code = ID wilayah di sana).
 * children_synced_at menandai wilayah yang anak-anaknya sudah diambil dari
 * API, sehingga kecamatan/kelurahan cukup diunduh sekali lalu permanen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->timestamp('children_synced_at')->nullable()->after('postal_code');
            $table->unique(['type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropUnique(['type', 'code']);
            $table->dropColumn('children_synced_at');
        });
    }
};
