<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads captured by the CS assistant: when a customer shares their name and/or
 * phone number in chat, the assistant emits a hidden data token and we upsert
 * it here (one row per chat session). Personal data — pruned with the stats
 * retention window by assistant:prune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_leads', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->string('name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_leads');
    }
};
