<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris barang pada DOKUMEN invoice/kuitansi bisa disunting admin tanpa
 * menyentuh order_items (yang mengikat stok & komisi). NULL = dokumen
 * menampilkan item pesanan apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('items_snapshot')->nullable()->after('customer_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('items_snapshot'));
    }
};
