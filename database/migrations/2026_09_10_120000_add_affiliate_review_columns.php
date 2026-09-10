<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribusi afiliator MANUAL (input pesanan admin / command) harus direview
 * super admin sebelum komisinya bisa cair: pesanan mencatat asal atribusi dan
 * siapa yang menginput; baris komisi mencatat penginput, reviewer, dan catatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('affiliate_source', 20)->nullable()->after('affiliate_id'); // referral|manual
            $table->foreignId('affiliate_attributed_by')->nullable()->after('affiliate_source')->constrained('users')->nullOnDelete();
        });

        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->foreignId('attributed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('attributed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attributed_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_note']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_attributed_by');
            $table->dropColumn('affiliate_source');
        });
    }
};
