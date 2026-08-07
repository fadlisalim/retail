<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp media: incoming photos/documents used to be flattened into the
 * message text ("[media] file.jpeg"), which showed a filename and nothing
 * else. Media now has its own columns so the inbox can render a thumbnail
 * and outgoing attachments can be sent through Wablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('media_url', 500)->nullable()->after('message');  // public/remote URL
            $table->string('media_type', 20)->nullable()->after('media_url'); // image, video, audio, document
            $table->string('media_name', 191)->nullable()->after('media_type'); // original filename
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_type', 'media_name']);
        });
    }
};
