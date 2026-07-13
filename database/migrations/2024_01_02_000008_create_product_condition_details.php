<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra disclosure fields for project-surplus / clearance / open-box / used
     * items (section 10). One row per applicable product.
     */
    public function up(): void
    {
        Schema::create('product_condition_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('reason_for_sale')->nullable();
            $table->string('item_location')->nullable();
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedSmallInteger('purchase_year')->nullable();
            $table->string('remaining_warranty')->nullable();
            $table->text('completeness')->nullable();
            $table->text('defect_notes')->nullable();
            $table->boolean('is_returnable')->default(false);
            $table->boolean('is_negotiable')->default(false);
            $table->boolean('pickup_required')->default(false);
            $table->boolean('auto_shipping')->default(true);
            $table->json('actual_condition_photos')->nullable(); // paths of real-condition photos
            $table->timestamps();

            $table->unique('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_condition_details');
    }
};
