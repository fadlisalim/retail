<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Variasi redaksi pesan campaign (digilir per penerima) + versi yang dipakai tiap pesan, untuk statistik per versi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->json('message_variants')->nullable()->after('message');
        });
        Schema::table('wa_campaign_messages', function (Blueprint $table) {
            $table->unsignedTinyInteger('variant')->nullable()->after('rendered_message');
        });
    }

    public function down(): void
    {
        Schema::table('wa_campaign_messages', fn (Blueprint $table) => $table->dropColumn('variant'));
        Schema::table('wa_campaigns', fn (Blueprint $table) => $table->dropColumn('message_variants'));
    }
};
