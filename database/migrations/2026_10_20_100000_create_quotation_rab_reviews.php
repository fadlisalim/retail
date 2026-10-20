<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review RAB lewat link rahasia (tanpa login): tim engineer / purchasing membuka
 * link ber-kunci, memeriksa modal & margin, lalu menyetujui atau minta revisi
 * dengan menulis namanya. Link RAB terpisah dari link customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('rab_token', 64)->nullable()->unique()->after('rab_note');
            $table->string('rab_approved_name', 120)->nullable()->after('rab_approved_by');
        });

        Schema::create('quotation_rab_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('reviewer_name', 120);
            $table->string('division', 40)->nullable();
            $table->string('decision', 20); // approved | revision
            $table->text('note')->nullable();
            $table->string('signature', 64)->nullable(); // sidik jari harga+modal saat direview
            $table->decimal('margin_percent', 6, 1)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_rab_reviews');
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique(['rab_token']);
            $table->dropColumn(['rab_token', 'rab_approved_name']);
        });
    }
};
