<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel tarif kargo per tujuan yang generik (satu baris = satu tujuan untuk
 * satu ekspedisi): minimum kg, harga per kg, dan pembagi volumetriknya.
 * Dipakai driver 'cargo_table' (mis. BR Cargo untuk kiriman proyek ≥ 50 kg).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_rates', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 40)->index();
            $table->string('origin', 60)->default('BANDUNG');
            $table->string('destination')->index();      // nama kota/kabupaten/kecamatan tujuan (huruf besar)
            $table->unsignedInteger('min_kg')->default(1);
            $table->decimal('price_per_kg', 15, 2);
            $table->unsignedInteger('volumetric_divisor')->default(4000);
            $table->timestamps();

            $table->unique(['provider_code', 'origin', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_rates');
    }
};
