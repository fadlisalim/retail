<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Usulan perbaikan dari agent otomatis: laporan, link ber-kunci untuk pemilik, OTP WA, keputusan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('sha', 40)->unique();
            $table->string('token', 64)->unique();
            $table->string('title');
            $table->longText('report');
            $table->string('pr_url');
            $table->unsignedInteger('pr_number');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|superseded
            $table->text('owner_note')->nullable();
            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamp('decided_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_proposals');
    }
};
