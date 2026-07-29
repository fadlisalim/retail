<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-site "Chat Toko" (Tokopedia-style seller chat): customers message the
 * store from the web — optionally with a product attached — and admins reply
 * from the admin panel. One row per message; conversations are grouped by the
 * per-browser session id (same id the CS assistant uses, so guest identity
 * from assistant_leads carries over). Logged-in customers also carry user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();  // customer account (if logged in)
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete(); // admin who replied
            $table->string('direction', 8);              // 'in' = customer → store, 'out' = admin → customer
            $table->text('message');
            $table->string('product_slug')->nullable();  // product attached to the message
            $table->boolean('is_read')->default(false);  // read by the receiving side
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_chat_messages');
    }
};
