<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shipping provider maps to a modular adapter class (manual, courier API,
        // aggregator...). `driver` selects the adapter; `config` holds non-secret opts.
        Schema::create('shipping_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('driver', 40)->default('manual'); // manual|flat|weight|freight|pickup|api
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_provider_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name'); // "Reguler", "Kargo", "Same Day"
            $table->string('type', 20)->default('regular'); // regular|cargo|pickup|fleet|manual
            // Volumetric divisor is per courier/service — NEVER hardcoded.
            $table->unsignedInteger('volumetric_divisor')->default(6000);
            $table->unsignedInteger('min_weight_grams')->default(1000);
            $table->unsignedInteger('max_weight_grams')->nullable();
            $table->string('estimated_days')->nullable(); // "2-3 hari"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shipping_provider_id', 'code']);
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('provinces')->nullable(); // matched against destination province
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price_per_kg', 15, 2)->default(0);
            $table->decimal('min_price', 15, 2)->default(0);
            $table->decimal('base_price', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['shipping_service_id', 'shipping_zone_id'], 'ship_rate_unique');
        });

        // Global shipping knobs (packing/handling/insurance/free-shipping threshold).
        Schema::create('shipping_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('packing_fee', 15, 2)->default(0);
            $table->decimal('handling_fee', 15, 2)->default(0);
            $table->decimal('insurance_percent', 5, 2)->default(0);
            $table->decimal('free_shipping_min_subtotal', 15, 2)->nullable();
            $table->unsignedInteger('default_volumetric_divisor')->default(6000);
            $table->unsignedInteger('weight_rounding_grams')->default(1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_settings');
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_services');
        Schema::dropIfExists('shipping_providers');
    }
};
