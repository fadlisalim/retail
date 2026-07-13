<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable();
            $table->string('service')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('status', 30)->default('pending'); // pending|shipped|delivered
            $table->unsignedInteger('billable_weight_grams')->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('proof_path')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('actual_weight_grams')->default(0);
            $table->unsignedInteger('volumetric_weight_grams')->default(0);
            $table->unsignedInteger('billable_weight_grams')->default(0);
            $table->decimal('length_cm', 8, 2)->default(0);
            $table->decimal('width_cm', 8, 2)->default(0);
            $table->decimal('height_cm', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->uuid('public_token')->unique(); // non-guessable public invoice URL
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('shipping', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->json('company_snapshot')->nullable();
            $table->json('customer_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('shipment_packages');
        Schema::dropIfExists('shipments');
    }
};
