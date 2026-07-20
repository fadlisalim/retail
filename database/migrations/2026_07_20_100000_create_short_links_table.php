<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URL shortener: maps a short code to a destination URL. Product + affiliate
 * (?ref=CODE) share links are shortened to energi.click/s/{code}. Referral
 * attribution still works because the redirect lands on the ?ref= URL, which the
 * global CaptureReferral middleware reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->text('url');
            $table->char('url_hash', 40)->unique(); // sha1(url) — dedupe long URLs
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
