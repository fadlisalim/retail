<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Catatan nilai terakhir yang ditulis seeder — dipakai SeedGuard agar suntingan admin tidak tertimpa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seed_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seed_snapshots');
    }
};
