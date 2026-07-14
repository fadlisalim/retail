<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indah_cargo_rates', function (Blueprint $table) {
            // Real Indonesian province (derived from Indah's area group) so checkout
            // can cascade province -> city. Nullable + indexed for the grouped lookup.
            $table->string('province')->nullable()->after('province_group')->index();
        });
    }

    public function down(): void
    {
        Schema::table('indah_cargo_rates', function (Blueprint $table) {
            $table->dropColumn('province');
        });
    }
};
