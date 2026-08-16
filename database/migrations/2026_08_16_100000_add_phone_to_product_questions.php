<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Q&A produk kini mensyaratkan nomor WA penanya: jawabannya dikirim ke WA
 * (nomor tampil tersensor di publik). wa_notified_at menandai jawaban yang
 * notifikasinya sudah terkirim supaya tidak dobel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_questions', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('name');
        });

        Schema::table('product_answers', function (Blueprint $table) {
            $table->timestamp('wa_notified_at')->nullable()->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('product_questions', fn (Blueprint $table) => $table->dropColumn('phone'));
        Schema::table('product_answers', fn (Blueprint $table) => $table->dropColumn('wa_notified_at'));
    }
};
