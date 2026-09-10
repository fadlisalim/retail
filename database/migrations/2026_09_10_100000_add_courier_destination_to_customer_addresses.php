<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID kelurahan tujuan versi RajaOngkir/Komerce pada alamat pelanggan —
 * dasar perhitungan ongkir kurir reguler (JNE, J&T, dst.). Alamat lama tanpa
 * ID akan dicari otomatis saat checkout lalu disimpan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('courier_destination_id')->nullable()->after('region_id');
            $table->string('courier_destination_label')->nullable()->after('courier_destination_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['courier_destination_id', 'courier_destination_label']);
        });
    }
};
