<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur "minta izin dulu": campaign jenis izin (pesan tanpa link/gambar, balas YA/STOP),
 * balasan YA otomatis dikirimi foto & link (followup_message). Kontak lama ridlabs yang
 * dulu dianggap "izin" karena hubungan bisnis kini harus membalas YA dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->text('followup_message')->nullable()->after('message');
        });
        Schema::table('wa_contacts', function (Blueprint $table) {
            $table->timestamp('permission_asked_at')->nullable()->after('consent_at');
        });

        DB::table('wa_contacts')->where('consent_status', 'opted_in')->where('consent_source', 'ridl')->update([
            'consent_status' => 'unknown',
            'consent_source' => null,
            'consent_at' => null,
            'consent_proof' => 'Izin otomatis customer lama ridlabs dicabut — perlu membalas YA atas pesan izin.',
        ]);
    }

    public function down(): void
    {
        Schema::table('wa_contacts', fn (Blueprint $table) => $table->dropColumn('permission_asked_at'));
        Schema::table('wa_campaigns', fn (Blueprint $table) => $table->dropColumn('followup_message'));
    }
};
