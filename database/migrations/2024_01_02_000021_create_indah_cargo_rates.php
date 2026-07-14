<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indah Cargo (Indah Logistik) per-destination tariff table, origin Bandung.
     * Air (udara/prioritas) and land/sea (darat/laut) rates are per kg.
     */
    public function up(): void
    {
        Schema::create('indah_cargo_rates', function (Blueprint $table) {
            $table->id();
            $table->string('origin')->default('BANDUNG');
            $table->string('destination_city')->index();
            $table->string('destination_code', 20)->nullable();
            $table->string('province_group')->nullable();
            $table->decimal('air_per_kg', 15, 2)->default(0);
            $table->decimal('land_per_kg', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['origin', 'destination_city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indah_cargo_rates');
    }
};
