<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            // KYC document photos — stored on the PRIVATE disk, served only to admins.
            $table->string('ktp_photo_path')->nullable()->after('address');
            $table->string('selfie_photo_path')->nullable()->after('ktp_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropColumn(['ktp_photo_path', 'selfie_photo_path']);
        });
    }
};
