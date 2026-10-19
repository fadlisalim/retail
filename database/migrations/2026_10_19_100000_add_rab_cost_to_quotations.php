<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RAB penawaran: modal (HPP) per baris + total modal & persetujuan RAB di header.
 * Modal hanya untuk internal — tidak pernah tampil di PDF / halaman customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('unit_price'); // null = modal belum diisi
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('cost_total', 15, 2)->nullable()->after('grand_total');
            $table->foreignId('rab_approved_by')->nullable()->after('handled_by')->constrained('users')->nullOnDelete();
            $table->timestamp('rab_approved_at')->nullable()->after('rab_approved_by');
            $table->string('rab_signature', 64)->nullable()->after('rab_approved_at'); // sidik jari harga+modal saat disetujui
            $table->string('rab_note', 500)->nullable()->after('rab_signature');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rab_approved_by');
            $table->dropColumn(['cost_total', 'rab_approved_at', 'rab_signature', 'rab_note']);
        });
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
