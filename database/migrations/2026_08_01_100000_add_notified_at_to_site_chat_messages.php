<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the admin reply that triggered a WhatsApp notification to the
 * customer. Stored in the database (not just cache) so the "one notification
 * per day per conversation" rule survives cache clears and deploys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_chat_messages', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('is_read');
        });
    }

    public function down(): void
    {
        Schema::table('site_chat_messages', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
