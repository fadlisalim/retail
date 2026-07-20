<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CS assistant logging, in two layers so retention can differ:
 *  - assistant_conversations: raw transcripts (may contain personal data a
 *    customer typed). Short retention (default 30 days).
 *  - assistant_daily_stats / assistant_daily_terms: non-personal aggregated
 *    rollups (counts only). Longer retention (default 180 days) so the admin
 *    dashboard keeps trends even after transcripts are pruned.
 * Pruned by the `assistant:prune` scheduled command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->nullable()->index();
            $table->text('message');
            $table->text('reply');
            $table->boolean('answered')->default(true); // false = fallback/offline reply
            $table->json('product_slugs')->nullable();
            $table->string('model', 64)->nullable();
            $table->string('ip_hash', 64)->nullable(); // hashed — not the raw IP
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('assistant_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('answered')->default(0);
            $table->unsignedInteger('fallbacks')->default(0);
            $table->unsignedInteger('sessions')->default(0);
        });

        Schema::create('assistant_daily_terms', function (Blueprint $table) {
            $table->id();
            $table->date('day')->index();
            $table->string('type', 16);   // 'keyword' | 'product'
            $table->string('term', 191);  // keyword text, or product slug
            $table->string('label')->nullable(); // product name for display
            $table->unsignedInteger('count')->default(0);
            $table->unique(['day', 'type', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_daily_terms');
        Schema::dropIfExists('assistant_daily_stats');
        Schema::dropIfExists('assistant_conversations');
    }
};
