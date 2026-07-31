<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-touch traffic attribution: one row per browser session, recording
 * where the visitor came from (Meta Ads, Instagram, Google, WhatsApp, direct…)
 * plus the landing page and UTM campaign. Later hits in the same session only
 * bump the page counter, so the table stays small. Pruned after ~3 months.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 64)->unique();   // hashed session id
            $table->string('source', 40)->index();           // meta_ads, instagram, google, direct…
            $table->string('medium', 30)->nullable();        // paid, organic, social, referral, none
            $table->string('campaign', 120)->nullable();     // utm_campaign
            $table->string('content', 120)->nullable();      // utm_content / ad set
            $table->string('referrer_host', 120)->nullable();
            $table->string('landing_path', 191)->nullable();
            $table->boolean('is_mobile')->default(false);
            $table->unsignedInteger('page_views')->default(1);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();       // hashed, never the raw IP
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();

            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
