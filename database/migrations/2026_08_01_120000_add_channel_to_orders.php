<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual/marketplace orders: sales that happened on Tokopedia, WhatsApp or
 * in the showroom are entered by an admin so the books, stock and customer
 * history live in one place. `channel` also lets reports split revenue per
 * sales channel; `thanks_sent_at` keeps the thank-you WhatsApp one-shot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 20)->default('website')->after('public_token');
            $table->string('external_reference', 60)->nullable()->after('channel'); // e.g. Tokopedia order no.
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('thanks_sent_at')->nullable()->after('paid_at');

            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['channel', 'created_at']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['channel', 'external_reference', 'thanks_sent_at']);
        });
    }
};
