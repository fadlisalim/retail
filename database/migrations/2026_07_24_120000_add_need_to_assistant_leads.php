<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The WhatsApp hand-off now asks the customer to leave name + WA number +
 * what they need before the CS number is revealed; `need` stores that note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_leads', function (Blueprint $table) {
            $table->string('need', 500)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_leads', function (Blueprint $table) {
            $table->dropColumn('need');
        });
    }
};
