<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo documentation per order: goods prepared, tested, packed, shipped,
 * installed. The customer sees their own order's photos on the tracking page;
 * photos flagged `is_public` also appear in the anonymous public gallery that
 * shows prospective buyers how orders are handled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_documentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);                 // persiapan, testing, packing, pengiriman, terpasang
            $table->string('path');                      // public disk
            $table->string('caption', 191)->nullable();
            $table->boolean('is_public')->default(false); // show in the public gallery
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'sort_order']);
            $table->index(['is_public', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documentations');
    }
};
