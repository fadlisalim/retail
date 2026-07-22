<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way WhatsApp inbox (via Wablas): incoming messages arrive on the webhook,
 * outgoing replies are sent from the admin chat page. One row per message,
 * conversations grouped by phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->index();
            $table->string('name')->nullable();          // WA pushName (incoming)
            $table->string('direction', 8);              // 'in' | 'out'
            $table->text('message');
            $table->string('wablas_id', 100)->nullable()->unique(); // dedupe webhook retries
            $table->boolean('is_read')->default(false);  // incoming: seen by admin?
            $table->boolean('sent_ok')->nullable();      // outgoing: Wablas accepted?
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // admin who replied
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
